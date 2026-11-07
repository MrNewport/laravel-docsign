<?php

use Illuminate\Support\Facades\Http;
use MrNewport\LaravelDocSign\Services\Signature\DocuSignPrivateSignatureProvider;
use MrNewport\LaravelDocSign\Services\Signature\PrivateSignatureRequest;
use MrNewport\LaravelDocSign\Services\Signature\PrivateSignatureSubmissionUnknown;

function demoSettings(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'docsign-demo-key-');
    file_put_contents($path, $pem);
    chmod($path, 0600);

    return ['enabled' => true, 'environment' => 'demo',
        'integration_key' => '11111111-1111-4111-8111-111111111111',
        'user_id' => '22222222-2222-4222-8222-222222222222',
        'account_id' => '33333333-3333-4333-8333-333333333333',
        'rsa_private_key_path' => $path, 'hmac_keys' => ['a-private-demo-hmac-key'],
        'connect_configuration_id' => '42'];
}

function privateRequest(): PrivateSignatureRequest
{
    $pdf = '%PDF-'.str_repeat('a', 120);

    return new PrivateSignatureRequest('44444444-4444-4444-8444-444444444444',
        $pdf, hash('sha256', $pdf), 'Fictional Resident', 'resident@example.test',
        'Review a fictional agreement', 2, 120, 320);
}

/** Encode an ephemeral OpenSSL RSA key as traditional PKCS#1 for compatibility. */
function traditionalRsaPem(): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $rsa = openssl_pkey_get_details($key)['rsa'];
    $length = static function (int $size): string {
        if ($size < 128) {
            return chr($size);
        }
        $bytes = '';
        while ($size > 0) {
            $bytes = chr($size & 255).$bytes;
            $size >>= 8;
        }

        return chr(128 + strlen($bytes)).$bytes;
    };
    $integer = static function (string $value) use ($length): string {
        $value = ltrim($value, "\x00");
        $value = $value === '' ? "\x00" : $value;
        if (ord($value[0]) >= 128) {
            $value = "\x00".$value;
        }

        return "\x02".$length(strlen($value)).$value;
    };
    $body = $integer("\x00");
    foreach (['n', 'e', 'd', 'p', 'q', 'dmp1', 'dmq1', 'iqmp'] as $field) {
        $body .= $integer($rsa[$field]);
    }
    $der = "\x30".$length(strlen($body)).$body;

    return "-----BEGIN RSA PRIVATE KEY-----\n"
        .chunk_split(base64_encode($der), 64, "\n")
        ."-----END RSA PRIVATE KEY-----\n";
}

it('accepts PKCS#8 and traditional PKCS#1 RSA keys but refuses malformed or non-RSA keys before HTTP', function () {
    $settings = demoSettings();
    $path = $settings['rsa_private_key_path'];
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-token']),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes' => Http::response([
            'envelopeId' => '55555555-5555-4555-8555-555555555555', 'status' => 'sent']),
    ]);
    (new DocuSignPrivateSignatureProvider($settings))->assertConfigured();
    expect((new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest())->status)->toBe('sent');
    file_put_contents($path, traditionalRsaPem());
    (new DocuSignPrivateSignatureProvider($settings))->assertConfigured();
    expect((new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest())->status)->toBe('sent');
    Http::assertSentCount(4);
    file_put_contents($path, '-----BEGIN RSA PRIVATE KEY-----'.str_repeat('x', 1200));
    expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->assertConfigured())
        ->toThrow(RuntimeException::class);
    expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
        ->toThrow(RuntimeException::class);
    $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1']);
    openssl_pkey_export($ec, $ecPem);
    file_put_contents($path, $ecPem);
    expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->assertConfigured())
        ->toThrow(RuntimeException::class);
    expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
        ->toThrow(RuntimeException::class);
    Http::assertSentCount(4);
    unlink($path);
});

it('is disabled and refuses an unapproved production configuration before HTTP', function () {
    Http::preventStrayRequests();
    $settings = demoSettings();
    foreach ([false, true] as $enabled) {
        $settings['enabled'] = $enabled;
        $settings['environment'] = $enabled ? 'production' : 'demo';
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
            ->toThrow(RuntimeException::class);
    }
    Http::assertNothingSent();
    unlink($settings['rsa_private_key_path']);
});

it('can repair failed OAuth before dispatch and reuses prepared account authorization for one envelope', function () {
    $settings = demoSettings();
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::sequence()
            ->push(['error' => 'consent_required'], 401)
            ->push(['access_token' => 'fictional-token'], 200),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes' => Http::response([
            'envelopeId' => '55555555-5555-4555-8555-555555555555', 'status' => 'sent']),
    ]);
    $provider = new DocuSignPrivateSignatureProvider($settings);
    expect(fn () => $provider->prepare())->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
    $provider->prepare();
    expect($provider->submit(privateRequest())->status)->toBe('sent');
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/envelopes')
        && $request['transactionId'] === privateRequest()->transactionId);
    unlink($settings['rsa_private_key_path']);
});

it('uses production OAuth and account-bound UserInfo base URI only after explicit activation', function () {
    $settings = demoSettings();
    $settings['environment'] = 'production';
    $settings['production_enabled'] = true;
    Http::preventStrayRequests();
    Http::fake([
        'https://account.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-live-token']),
        'https://account.docusign.com/oauth/userinfo' => Http::response(['accounts' => [[
            'account_id' => $settings['account_id'], 'base_uri' => 'https://na2.docusign.net']]]),
        'https://na2.docusign.net/restapi/v2.1/accounts/*/envelopes' => Http::response([
            'envelopeId' => '55555555-5555-4555-8555-555555555555', 'status' => 'sent']),
    ]);
    $sent = (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest());
    expect($sent->environment)->toBe('production');
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'na2.docusign.net/restapi/v2.1/accounts/')
        && $request['transactionId'] === privateRequest()->transactionId);
    unlink($settings['rsa_private_key_path']);
});

it('refuses a foreign or untrusted production UserInfo URI before sending an envelope', function () {
    $settings = demoSettings();
    $settings['environment'] = 'production';
    $settings['production_enabled'] = true;
    foreach ([['account_id' => '66666666-6666-4666-8666-666666666666',
        'base_uri' => 'https://na2.docusign.net'],
        ['account_id' => $settings['account_id'],
            'base_uri' => 'https://na2.docusign.net.evil.example']] as $account) {
        Http::preventStrayRequests();
        Http::fake([
            'https://account.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-live-token']),
            'https://account.docusign.com/oauth/userinfo' => Http::response(['accounts' => [$account]]),
        ]);
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
            ->toThrow(RuntimeException::class);
        Http::assertSentCount(2);
    }
    unlink($settings['rsa_private_key_path']);
});

it('sends exactly one bounded private PDF with a stable transaction and reviewed signer', function () {
    $settings = demoSettings();
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-token']),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes' => Http::response([
            'envelopeId' => '55555555-5555-4555-8555-555555555555', 'status' => 'sent']),
    ]);
    $request = privateRequest();
    $receipt = (new DocuSignPrivateSignatureProvider($settings))->submit($request);
    expect($receipt->transactionId)->toBe($request->transactionId)
        ->and($receipt->environment)->toBe('demo')
        ->and($receipt->status)->toBe('sent');
    Http::assertSentCount(2);
    Http::assertSent(fn ($sent) => str_contains($sent->url(), '/envelopes')
        && $sent['transactionId'] === $request->transactionId
        && base64_decode($sent['documents'][0]['documentBase64'], true) === $request->pdfBytes
        && $sent['recipients']['signers'][0]['email'] === $request->signerEmail
        && $sent['recipients']['signers'][0]['tabs']['signHereTabs'][0]['pageNumber'] === '2'
        && $sent['status'] === 'sent');
    unlink($settings['rsa_private_key_path']);
});

it('keeps a server error ambiguous and reconciles only the same transaction', function () {
    $settings = demoSettings();
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-token']),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes*' => Http::sequence()
            ->push('temporary', 503)->push(['envelopeTransactionStatuses' => [[
                'transactionId' => '44444444-4444-4444-8444-444444444444',
                'envelopeId' => '55555555-5555-4555-8555-555555555555',
                'status' => 'sent']]]),
    ]);
    $provider = new DocuSignPrivateSignatureProvider($settings);
    expect(fn () => $provider->submit(privateRequest()))->toThrow(PrivateSignatureSubmissionUnknown::class);
    $found = $provider->reconcile(privateRequest()->transactionId);
    expect($found->envelopeId)->toBe('55555555-5555-4555-8555-555555555555');
    Http::assertSentCount(3);
    Http::assertSent(fn ($sent) => str_contains($sent->url(), '?transaction_ids=44444444-4444-4444-8444-444444444444'));
    unlink($settings['rsa_private_key_path']);
});

it('authenticates raw Connect bytes and refuses forged or foreign account callbacks', function () {
    $settings = demoSettings();
    $provider = new DocuSignPrivateSignatureProvider($settings);
    $body = json_encode(['event' => 'envelope-completed', 'configurationId' => '42',
        'data' => ['accountId' => $settings['account_id'],
            'envelopeId' => '55555555-5555-4555-8555-555555555555']], JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $body, $settings['hmac_keys'][0], true));
    $event = $provider->verifyWebhook($body, ['X-DocuSign-Signature-1' => [$signature]]);
    expect($event->event)->toBe('envelope-completed')
        ->and($event->bodySha256)->toBe(hash('sha256', $body));
    expect(fn () => $provider->verifyWebhook($body.' ', ['X-DocuSign-Signature-1' => [$signature]]))
        ->toThrow(InvalidArgumentException::class);
    $foreign = str_replace($settings['account_id'], '66666666-6666-4666-8666-666666666666', $body);
    $foreignSignature = base64_encode(hash_hmac('sha256', $foreign, $settings['hmac_keys'][0], true));
    expect(fn () => $provider->verifyWebhook($foreign, ['X-DocuSign-Signature-1' => [$foreignSignature]]))
        ->toThrow(InvalidArgumentException::class);
    unlink($settings['rsa_private_key_path']);
});

it('requires authenticated completed status and retains exact signed PDF and certificate bytes', function () {
    $settings = demoSettings();
    $id = '55555555-5555-4555-8555-555555555555';
    $signed = '%PDF-'.str_repeat('s', 120);
    $certificate = '%PDF-'.str_repeat('c', 120);
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-token']),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes/'.$id.'/documents/combined' => Http::response($signed, 200),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes/'.$id.'/documents/certificate' => Http::response($certificate, 200),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes/'.$id => Http::response([
            'envelopeId' => $id, 'status' => 'completed']),
    ]);
    $files = (new DocuSignPrivateSignatureProvider($settings))->completedArtifacts($id);
    expect($files['signed_pdf']['bytes'])->toBe($signed)
        ->and($files['signed_pdf']['sha256'])->toBe(hash('sha256', $signed))
        ->and($files['certificate_pdf']['bytes'])->toBe($certificate);
    unlink($settings['rsa_private_key_path']);
});

it('uses one bounded in-memory RSA key source and refuses conflicts or invalid keys before HTTP', function () {
    $settings = demoSettings();
    $path = $settings['rsa_private_key_path'];
    $pem = file_get_contents($path);
    $settings['rsa_private_key_path'] = '';
    $settings['rsa_private_key_pem'] = $pem;
    unlink($path);
    Http::preventStrayRequests();
    Http::fake([
        'https://account-d.docusign.com/oauth/token' => Http::response(['access_token' => 'fictional-token']),
        'https://demo.docusign.net/restapi/v2.1/accounts/*/envelopes' => Http::response([
            'envelopeId' => '55555555-5555-4555-8555-555555555555', 'status' => 'sent']),
    ]);
    expect((new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest())->status)->toBe('sent');
    $settings['rsa_private_key_pem'] = traditionalRsaPem();
    expect((new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest())->status)->toBe('sent');
    Http::assertSentCount(4);

    $settings['rsa_private_key_path'] = tempnam(sys_get_temp_dir(), 'docsign-conflict-');
    file_put_contents($settings['rsa_private_key_path'], $pem);
    try {
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->assertConfigured())
            ->toThrow(RuntimeException::class);
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
            ->toThrow(RuntimeException::class);
    } finally {
        unlink($settings['rsa_private_key_path']);
    }
    $settings['rsa_private_key_path'] = '';
    $invalid = ['-----BEGIN RSA PRIVATE KEY-----'.str_repeat('x', 1200)];
    $weak = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($weak, $weakPem);
    $invalid[] = $weakPem;
    $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1']);
    openssl_pkey_export($ec, $ecPem);
    $invalid[] = $ecPem;
    foreach ($invalid as $candidate) {
        $settings['rsa_private_key_pem'] = $candidate;
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->assertConfigured())
            ->toThrow(RuntimeException::class, 'The configured Docusign RSA key is unavailable.');
        expect(fn () => (new DocuSignPrivateSignatureProvider($settings))->submit(privateRequest()))
            ->toThrow(RuntimeException::class);
    }
    Http::assertSentCount(4);
});
