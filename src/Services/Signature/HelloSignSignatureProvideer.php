<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use Illuminate\Http\Request;
use MrNewport\LaravelDocSign\Models\Document;

class DocuSignSignatureProvider implements SignatureProviderInterface
{
    protected string $apiKey;
    protected string $callbackUrl;

    public function __construct()
    {
        $this->apiKey = config('docsign.signature.providers.docusign.api_key','');
        $this->callbackUrl = config('docsign.signature.providers.docusign.callback_url','/docsign/docusign/callback');
    }

    public function createSignatureRequest(Document $document, array $signers): array
    {
        return [
            'url' => 'https://demo.docusign.net/signing-url-'.uniqid(),
            'info' => 'DocuSign request initiated.'
        ];
    }

    public function processCallback(Request $request): void
    {
        $docId = $request->input('doc_id');
        if ($docId) {
            $doc = Document::find($docId);
            if ($doc) {
                $doc->status = 'completed';
                $doc->save();
            }
        }
    }
}
