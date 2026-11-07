<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use InvalidArgumentException;

/** Source-neutral request. The application retains authorization and the private PDF. */
final readonly class PrivateSignatureRequest
{
    public function __construct(
        public string $transactionId,
        public string $pdfBytes,
        public string $pdfSha256,
        public string $signerName,
        public string $signerEmail,
        public string $subject,
        public int $page,
        public int $x,
        public int $y,
    ) {
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $transactionId)
            || ! str_starts_with($pdfBytes, '%PDF-')
            || strlen($pdfBytes) > 25 * 1024 * 1024
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $pdfSha256)
            || ! hash_equals($pdfSha256, hash('sha256', $pdfBytes))
            || trim($signerName) === '' || mb_strlen($signerName) > 100
            || ! filter_var($signerEmail, FILTER_VALIDATE_EMAIL)
            || trim($subject) === '' || mb_strlen($subject) > 100
            || $page < 1 || $page > 1000 || $x < 0 || $x > 2000 || $y < 0 || $y > 2000) {
            throw new InvalidArgumentException('A bounded exact private signing source and reviewed signer position are required.');
        }
    }
}
