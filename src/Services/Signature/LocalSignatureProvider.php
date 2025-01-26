<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Request;
use MrNewport\LaravelDocSign\Models\Document;

class LocalSignatureProvider implements SignatureProviderInterface
{
    protected string $callbackUrl;

    public function __construct()
    {
        $this->callbackUrl = config('docsign.signature.providers.local.callback_url', '/docsign/local/callback');
    }

    public function createSignatureRequest(Document $document, array $signers): array
    {
        return [
            'url' => url($this->callbackUrl.'?doc_id='.$document->id),
            'info' => 'Local signature started'
        ];
    }

    public function processCallback(Request $request): void
    {
        $docId = $request->query('doc_id');
        if ($docId) {
            $doc = Document::find($docId);
            if ($doc) {
                $doc->status = 'completed';
                $doc->save();
            }
        }
    }
}
