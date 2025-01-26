<?php

use MrNewport\LaravelDocSign\Models\Document;
use MrNewport\LaravelDocSign\Facades\DocSign;

it('generates a PDF for a document', function () {
    $doc = Document::create([
        'title' => 'Test Doc',
        'data' => ['_template' => 'docsign::test','name'=>'John']
    ]);

    $path = DocSign::generate($doc);
    expect($doc->file_path)->toBe($path)
        ->and($doc->status)->toBe('draft');
});
