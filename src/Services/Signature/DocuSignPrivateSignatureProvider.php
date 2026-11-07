<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/** Private-byte Docusign transport. Application authority stays outside this package. */
final class DocuSignPrivateSignatureProvider implements PrivateSignatureProviderInterface
{
    private const MAX_ARTIFACT_BYTES = 3145728;

    private ?string $resolvedBaseUri = null;

    private ?string $preparedToken = null;

    private int $preparedUntil = 0;

    public function __construct(private ?array $settings = null) {}

    /** Local-only configuration gate; no provider or token request is made. */
    public function assertConfigured(): void
    {
        $this->readKey($this->ready());
    }

    /** Authorize the account without sending an envelope or recipient data. */
    public function prepare(): void
    {
        $this->token($this->ready());
    }

    public function submit(PrivateSignatureRequest $request): PrivateSignatureSubmission
    {
        $config = $this->ready();
        $token = $this->token($config);
        $body = [
            'transactionId' => $request->transactionId,
            'emailSubject' => $request->subject,
            'status' => 'sent',
            'documents' => [[
                'documentBase64' => base64_encode($request->pdfBytes),
                'name' => 'Reviewed agreement.pdf', 'fileExtension' => 'pdf', 'documentId' => '1',
            ]],
            'recipients' => ['signers' => [[
                'email' => $request->signerEmail, 'name' => $request->signerName,
                'recipientId' => '1', 'routingOrder' => '1',
                'tabs' => ['signHereTabs' => [[
                    'documentId' => '1', 'pageNumber' => (string) $request->page,
                    'recipientId' => '1', 'xPosition' => (string) $request->x,
                    'yPosition' => (string) $request->y,
                ]]],
            ]]],
        ];
        try {
            $response = Http::withToken($token)->withOptions(['allow_redirects' => false])
                ->timeout(20)->post($this->endpoint($config, 'envelopes'), $body);
        } catch (ConnectionException $error) {
            throw new PrivateSignatureSubmissionUnknown('The envelope response was lost; reconcile this transaction before any retry.', 0, $error);
        }
        if ($response->serverError()) {
            throw new PrivateSignatureSubmissionUnknown('The provider did not confirm whether this envelope was sent.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('The provider rejected this envelope request.');
        }
        $id = $response->json('envelopeId');
        $status = $response->json('status');
        if (! self::uuid($id) || ! in_array($status, ['sent', 'delivered', 'completed'], true)) {
            throw new PrivateSignatureSubmissionUnknown('The provider response did not prove a sent envelope.');
        }

        return new PrivateSignatureSubmission('docusign', $config['environment'], $config['account_id'],
            $request->transactionId, strtolower($id), $status);
    }

    public function reconcile(string $transactionId): ?PrivateSignatureSubmission
    {
        if (! self::uuid($transactionId)) {
            throw new InvalidArgumentException('A canonical transaction ID is required.');
        }
        $config = $this->ready();
        $response = Http::withToken($this->token($config))->withOptions(['allow_redirects' => false])
            ->timeout(15)->get($this->endpoint($config, 'envelopes'),
                ['transaction_ids' => $transactionId]);
        if (! $response->successful()) {
            throw new RuntimeException('The provider transaction lookup is unavailable.');
        }
        $matches = $response->json('envelopeTransactionStatuses');
        if (! is_array($matches)) {
            throw new RuntimeException('The provider transaction lookup was malformed.');
        }
        if ($matches === []) {
            return null;
        }
        if (count($matches) !== 1 || ! is_array($matches[0])
            || ! hash_equals(strtolower($transactionId), strtolower((string) ($matches[0]['transactionId'] ?? '')))
            || ! self::uuid($matches[0]['envelopeId'] ?? null)
            || ! in_array($matches[0]['status'] ?? null, ['sent', 'delivered', 'completed', 'declined', 'voided'], true)) {
            throw new RuntimeException('The provider transaction lookup needs manual recovery.');
        }

        return new PrivateSignatureSubmission('docusign', $config['environment'], $config['account_id'],
            strtolower($transactionId), strtolower($matches[0]['envelopeId']),
            (string) ($matches[0]['status'] ?? 'unknown'));
    }

    public function verifyWebhook(string $rawBody, array $headers): VerifiedPrivateSignatureEvent
    {
        $config = $this->ready();
        if ($rawBody === '' || strlen($rawBody) > 65536) {
            throw new InvalidArgumentException('A bounded raw Connect message is required.');
        }
        $provided = [];
        foreach ($headers as $name => $values) {
            if (preg_match('/\Ax-docusign-signature-[1-9][0-9]*\z/iD', (string) $name)) {
                foreach ((array) $values as $value) {
                    $provided[] = (string) $value;
                }
            }
        }
        $valid = false;
        foreach ($config['hmac_keys'] as $key) {
            $expected = base64_encode(hash_hmac('sha256', $rawBody, $key, true));
            foreach ($provided as $signature) {
                $valid = hash_equals($expected, $signature) || $valid;
            }
        }
        if (! $valid) {
            throw new InvalidArgumentException('Connect HMAC verification failed.');
        }
        try {
            $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('Connect JSON is malformed.', 0, $error);
        }
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        $id = is_array($data) ? ($data['envelopeId'] ?? null) : null;
        $account = is_array($data) ? ($data['accountId'] ?? null) : null;
        $event = is_array($payload) ? ($payload['event'] ?? null) : null;
        if (! self::uuid($id) || $account !== $config['account_id']
            || ! in_array($event, ['envelope-sent', 'envelope-delivered',
                'envelope-completed', 'envelope-declined', 'envelope-voided'], true)
            || (string) ($payload['configurationId'] ?? '') !== $config['connect_configuration_id']) {
            throw new InvalidArgumentException('Connect account, configuration or event differs.');
        }

        return new VerifiedPrivateSignatureEvent('docusign', $config['environment'], $account,
            strtolower($id), $event, hash('sha256', $rawBody));
    }

    public function status(string $envelopeId): string
    {
        $config = $this->ready();
        $this->assertEnvelope($envelopeId);
        $response = Http::withToken($this->token($config))->withOptions(['allow_redirects' => false])
            ->timeout(15)->get($this->endpoint($config, 'envelopes/'.strtolower($envelopeId)));
        if (! $response->successful() || ! hash_equals(strtolower($envelopeId),
            strtolower((string) $response->json('envelopeId')))) {
            throw new RuntimeException('The provider envelope cannot be verified.');
        }

        return (string) $response->json('status');
    }

    public function completedArtifacts(string $envelopeId): array
    {
        $config = $this->ready();
        $this->assertEnvelope($envelopeId);
        if ($this->status($envelopeId) !== 'completed') {
            throw new RuntimeException('The provider envelope is not complete.');
        }
        $token = $this->token($config);
        $root = 'envelopes/'.strtolower($envelopeId).'/documents/';
        $out = [];
        foreach (['combined' => 'signed_pdf', 'certificate' => 'certificate_pdf'] as $path => $name) {
            $response = Http::withToken($token)->withOptions([
                'allow_redirects' => false,
                'on_headers' => static function (ResponseInterface $response): void {
                    $length = $response->getHeaderLine('Content-Length');
                    if ($length !== '' && ctype_digit($length)
                        && (int) $length > self::MAX_ARTIFACT_BYTES) {
                        throw new RuntimeException('The completed provider artifact exceeds the private storage limit.');
                    }
                },
                'progress' => static function (int $expected, int $downloaded): void {
                    if ($downloaded > self::MAX_ARTIFACT_BYTES) {
                        throw new RuntimeException('The completed provider artifact exceeds the private storage limit.');
                    }
                },
            ])
                ->timeout(30)->get($this->endpoint($config, $root.$path));
            $bytes = $response->body();
            if (! $response->successful() || ! str_starts_with($bytes, '%PDF-')
                || strlen($bytes) < 100 || strlen($bytes) > self::MAX_ARTIFACT_BYTES) {
                throw new RuntimeException('The completed provider artifact is unavailable.');
            }
            $out[$name] = ['bytes' => $bytes, 'sha256' => hash('sha256', $bytes),
                'byte_count' => strlen($bytes)];
        }

        return $out;
    }

    private function token(array $config): string
    {
        if ($this->preparedToken !== null && $this->resolvedBaseUri !== null
            && $this->preparedUntil > time() + 60) {
            return $this->preparedToken;
        }
        $key = $this->readKey($config);
        $part = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value,
            JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $now = time();
        $authHost = $config['environment'] === 'demo'
            ? 'account-d.docusign.com' : 'account.docusign.com';
        $unsigned = $part(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$part([
            'iss' => $config['integration_key'], 'sub' => $config['user_id'],
            'aud' => $authHost, 'iat' => $now, 'exp' => $now + 3600,
            'scope' => 'signature impersonation']);
        if (! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Docusign JWT signing failed.');
        }
        $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $response = Http::asForm()->withOptions(['allow_redirects' => false])->timeout(15)
            ->post('https://'.$authHost.'/oauth/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Docusign authorization is unavailable.');
        }
        if ($config['environment'] === 'demo') {
            $this->resolvedBaseUri = 'https://demo.docusign.net';
        } else {
            $userinfo = Http::withToken($token)->withOptions(['allow_redirects' => false])
                ->timeout(15)->get('https://account.docusign.com/oauth/userinfo');
            if (! $userinfo->successful() || ! is_array($userinfo->json('accounts'))) {
                throw new RuntimeException('The production Docusign account binding is unavailable.');
            }
            $accounts = array_values(array_filter($userinfo->json('accounts'),
                static fn (mixed $account): bool => is_array($account)
                    && ($account['account_id'] ?? null) === $config['account_id']));
            $base = count($accounts) === 1 ? ($accounts[0]['base_uri'] ?? null) : null;
            if (! is_string($base) || preg_match(
                '/\Ahttps:\/\/(?!demo\.)[a-z0-9-]+\.docusign\.net\z/iD', $base) !== 1) {
                throw new RuntimeException('The production Docusign base URI is not trusted.');
            }
            $this->resolvedBaseUri = strtolower($base);
        }

        $this->preparedToken = $token;
        $this->preparedUntil = $now + 3300;

        return $token;
    }

    private function readKey(array $config): \OpenSSLAsymmetricKey
    {
        $path = $config['rsa_private_key_path'] ?? '';
        $inline = $config['rsa_private_key_pem'] ?? '';
        $pem = $inline !== '' ? $inline : @file_get_contents($path);
        if (! is_string($pem) || strlen($pem) < 1000 || strlen($pem) > 16384) {
            throw new RuntimeException('The configured Docusign RSA key is unavailable.');
        }
        $key = is_string($pem) ? openssl_pkey_get_private($pem) : false;
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($key === false || ! is_array($details)
            || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA
            || ($details['bits'] ?? 0) < 2048) {
            throw new RuntimeException('The configured Docusign RSA key is unavailable.');
        }

        return $key;
    }

    private function ready(): array
    {
        $config = $this->settings ?? config('docsign.private_docusign', []);
        $path = $config['rsa_private_key_path'] ?? '';
        $inline = $config['rsa_private_key_pem'] ?? '';
        $hasPath = is_string($path) && $path !== '';
        $hasInline = is_string($inline) && $inline !== '';
        if (($config['enabled'] ?? null) !== true
            || ! in_array($config['environment'] ?? null, ['demo', 'production'], true)
            || (($config['environment'] ?? null) === 'production'
                && ($config['production_enabled'] ?? null) !== true)
            || ! self::uuid($config['integration_key'] ?? null)
            || ! self::uuid($config['user_id'] ?? null)
            || ! self::uuid($config['account_id'] ?? null)
            || ! is_string($path) || ! is_string($inline)
            || $hasPath === $hasInline
            || ($hasPath && (! str_starts_with($path, '/') || ! is_file($path) || is_link($path)))
            || ! is_array($config['hmac_keys'] ?? null)
            || $config['hmac_keys'] === []
            || count(array_filter($config['hmac_keys'], static fn ($key): bool =>
                is_string($key) && strlen($key) >= 16)) !== count($config['hmac_keys'])
            || ! preg_match('/\A[1-9][0-9]*\z/D', (string) ($config['connect_configuration_id'] ?? ''))) {
            throw new RuntimeException('Docusign private signing is disabled or incomplete.');
        }

        return $config;
    }

    private function endpoint(array $config, string $path): string
    {
        if ($this->resolvedBaseUri === null) {
            throw new RuntimeException('The Docusign account base URI was not verified.');
        }

        return $this->resolvedBaseUri.'/restapi/v2.1/accounts/'.$config['account_id'].'/'.$path;
    }

    private function assertEnvelope(string $id): void
    {
        if (! self::uuid($id)) {
            throw new InvalidArgumentException('A canonical envelope ID is required.');
        }
    }

    private static function uuid(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $value) === 1;
    }
}
