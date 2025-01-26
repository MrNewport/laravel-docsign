<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Request;
use MrNewport\LaravelDocSign\Models\Document;

interface SignatureProviderInterface
{
    public function createSignatureRequest(Document $document, array $signers): array;

    public function processCallback(Request $request): void;
}
