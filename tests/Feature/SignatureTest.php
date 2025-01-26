<?php

use MrNewport\LaravelDocSign\Facades\DocSign;
use MrNewport\LaravelDocSign\Models\Document;

it('requests a signature with default provider', function () {
    $doc = Document::create(['title' => 'SignMe']);
    $result = DocSign::requestSignature($doc, [['name'=>'Alice','email'=>'alice@example.com']]);
    expect($doc->status)->toBe('signing')
        ->and($result)->toHaveKey('url');
});

it('handles callback', function () {
    $doc = Document::create(['title'=>'CallbackDoc','status'=>'signing']);
    $this->post('/docsign/callback/local?doc_id='.$doc->id)->assertOk();
    $doc->refresh();
    expect($doc->status)->toBe('completed');
});
