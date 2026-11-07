<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

final readonly class VerifiedPrivateSignatureEvent
{
    public function __construct(
        public string $provider,
        public string $environment,
        public string $accountId,
        public string $envelopeId,
        public string $event,
        public string $bodySha256,
    ) {}
}
