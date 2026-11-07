<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

final readonly class PrivateSignatureSubmission
{
    public function __construct(
        public string $provider,
        public string $environment,
        public string $accountId,
        public string $transactionId,
        public string $envelopeId,
        public string $status,
    ) {}
}
