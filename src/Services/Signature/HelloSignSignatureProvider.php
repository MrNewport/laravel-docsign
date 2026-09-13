<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Request;
use MrNewport\LaravelDocSign\Models\Document;

/** Reserved adapter name. Bind a verified provider implementation before use. */
class HelloSignSignatureProvider implements SignatureProviderInterface
{
    public function createSignatureRequest(Document $document, array $signers): array
    {
        throw new \LogicException('This provider is not implemented. Configure a SignatureProviderInterface adapter.');
    }

    public function processCallback(Request $request): void
    {
        throw new \LogicException('This provider is not implemented. No document has been changed.');
    }
}
