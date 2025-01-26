<?php

namespace MrNewport\LaravelDocSign\Facades;

use Illuminate\Support\Facades\Facade;
use MrNewport\LaravelDocSign\Models\Document;

/**
 * @method static string generate(Document $document)
 * @method static array requestSignature(Document $document, array $signers)
 * @method static void handleCallback(string $provider, \Illuminate\Http\Request $request)
 */
class DocSign extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'docsign.manager';
    }
}
