<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

interface PrivateSignatureProviderInterface
{
    public function submit(PrivateSignatureRequest $request): PrivateSignatureSubmission;

    /** A lost submit response never authorizes a second send. */
    public function reconcile(string $transactionId): ?PrivateSignatureSubmission;

    public function verifyWebhook(string $rawBody, array $headers): VerifiedPrivateSignatureEvent;

    /** Returns a provider status fetched with authenticated API access. */
    public function status(string $envelopeId): string;

    /** Returns completed PDF and certificate bytes; no application storage or disclosure. */
    public function completedArtifacts(string $envelopeId): array;
}
