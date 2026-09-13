<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use MrNewport\LaravelDocSign\Models\Document;

/** Local demonstration only: this does not collect a legally verified signature. */
class LocalSignatureProvider implements SignatureProviderInterface
{
    public function createSignatureRequest(Document $document, array $signers): array
    {
        $this->assertEnabled();
        return [
            'url' => URL::temporarySignedRoute('docsign.callback', now()->addMinutes(15), [
                'provider' => 'local', 'doc_id' => $document->getKey(),
            ]),
            'info' => 'Local demonstration callback; submit via POST. No external signature request was sent.',
        ];
    }

    public function processCallback(Request $request): void
    {
        $this->assertEnabled();
        abort_unless($request->hasValidSignature(), 403);
        $document = Document::query()->findOrFail($request->query('doc_id'));
        abort_unless($document->signature_provider === 'local' && $document->status === 'signing', 409);
        $document->update(['status' => 'completed']);
    }

    private function assertEnabled(): void
    {
        if (!config('docsign.signature.providers.local.enabled', false) || !config('docsign.callbacks.enabled', false)) {
            throw new \LogicException('The local signature demonstration and callbacks must be explicitly enabled.');
        }
    }
}
