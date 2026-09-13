<?php

use MrNewport\LaravelDocSign\Facades\DocSign;
use MrNewport\LaravelDocSign\Models\Document;

it('completes only a signed local demonstration callback', function () {
    $doc = Document::create(['title' => 'SignMe']);
    $result = DocSign::requestSignature($doc, [['name' => 'Alice', 'email' => 'alice@example.com']]);
    expect($doc->status)->toBe('signing');
    $this->post($result['url'])->assertOk();
    expect($doc->fresh()->status)->toBe('completed');
    $this->post($result['url'])->assertStatus(409);
});

it('rejects forged and expired callbacks without changing the document', function () {
    $doc = Document::create(['title' => 'Example']);
    $result = DocSign::requestSignature($doc, []);
    $this->post('/docsign/callback/local?doc_id='.$doc->id)->assertForbidden();
    $this->travel(16)->minutes();
    $this->post($result['url'])->assertForbidden();
    expect($doc->fresh()->status)->toBe('signing');
});

it('does not fake success or change state for unimplemented providers', function (string $provider) {
    config(['docsign.signature.default' => $provider]);
    $doc = Document::create(['title' => 'Example', 'status' => 'draft']);
    expect(fn () => DocSign::requestSignature($doc, []))->toThrow(LogicException::class);
    expect($doc->fresh()->status)->toBe('draft');
})->with(['docusign', 'hellosign']);

it('requires explicit opt in to the local demonstration', function () {
    config(['docsign.signature.providers.local.enabled' => false]);
    $doc = Document::create(['title' => 'Example']);
    expect(fn () => DocSign::requestSignature($doc, []))->toThrow(LogicException::class);
});
