# Laravel DocSign handbook

DocSign renders trusted Blade/Twig templates to PDF, stores generated documents and delegates signature requests/callbacks through a provider interface. PDF generation is implemented. Built-in DocuSign/HelloSign classes are reserved adapters that throw; they are not live external signing integrations. The local provider is an explicitly enabled state-transition demonstration and does not collect or verify a person's signature.

## Fresh Laravel installation

```sh
composer create-project laravel/laravel documents-app '^12.0'
cd documents-app
composer require mrnewport/laravel-docsign:^2.0
php artisan vendor:publish --tag=docsign-config
php artisan migrate
```

Use `'^13.0'` and its supported PHP runtime for Laravel 13. Supported installations are Laravel 12 / PHP 8.2+ and Laravel 13 with its framework requirements. If a registry has not indexed the tag, add `composer config repositories.docsign vcs https://github.com/MrNewport/laravel-docsign` and repeat the requirement. Laravel discovers DocSignServiceProvider, merges config, loads the documents migration/views and binds singleton `docsign.manager`. The facade is `MrNewport\LaravelDocSign\Facades\DocSign`; the similarly named root `MrNewport\LaravelDocSign\DocSign` class is not the manager/facade API.

No public callback route exists by default. No UI/frontend build or signature-provider account is needed for standalone PDF generation. Choose and configure storage before generating files. A local development disk URL is not an authorized private-download mechanism.

## Generate a first document

```php
use MrNewport\LaravelDocSign\Models\Document;
use MrNewport\LaravelDocSign\Facades\DocSign;

$document = Document::create([
    'title' => 'Service agreement',
    'data' => ['content' => 'Services and terms reviewed by the parties.'],
]);
$url = DocSign::generate($document);
```

The default Blade view `docsign::default` receives title/content and escapes them. `generate(Document): string` resolves the configured template/PDF engine, renders HTML, renders PDF bytes, writes `documents/<UUID>.pdf` on storage_disk, gets that disk's URL, saves file_path/status=draft and returns the URL. A storage put returning false raises RuntimeException. Template/render/storage exceptions propagate. It is a synchronous operation, not a queued job.

Use an application Blade template for a real agreement:

```php
$document = Document::create([
    'title' => 'Maintenance agreement',
    'data' => [
        '_template' => 'documents.maintenance',
        'customer_name' => 'Avery Example',
        'service_description' => 'Quarterly inspection',
    ],
]);
```

Create `resources/views/documents/maintenance.blade.php`:

```blade
<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body>
    <h1>{{ $title }}</h1>
    <p>Customer: {{ $customer_name }}</p>
    <p>{{ $service_description }}</p>
</body></html>
```

`_template` and template source are trusted application configuration; do not let users upload arbitrary Blade or choose arbitrary view names. Blade is executable application code, not a sandbox. Capture immutable template/revision/data snapshots in the host when the accepted document is evidence.

## Template engines

`TemplateEngineInterface::render(array $data, string $title = ''): string` is the common contract. BladeTemplateEngine chooses `_template` or `docsign::default` and passes title plus data; the explicit method title takes precedence over a data title key. A standalone call can render HTML without creating a Document:

```php
$html = (new BladeTemplateEngine)->render(['content' => 'Inspection terms'], 'Agreement');
```

TwigTemplateEngine chooses `_template_str` or `Hello {{ name }}` and renders the supplied data with a Twig ArrayLoader. Its method title argument is not automatically inserted into the Twig data. Include `title` yourself where needed, and explicitly escape untrusted values (`{{ customer_name|e }}`); the current engine creates Twig without an autoescape setting. Trusted templates remain required.

```php
config(['docsign.template_engine' => 'twig']);
$document->data = [
    '_template_str' => '<h1>{{ title|e }}</h1><p>{{ customer_name|e }}</p>',
    'title' => 'Agreement', 'customer_name' => 'Avery Example',
];
$document->save();
```

For a custom engine bind `docsign.template_engine.NAME` to an implementation and set template_engine=NAME. Built-in names blade/twig resolve their concrete classes directly, so use a new name for the named extension path. Host template restrictions and escaping policy remain explicit.

## PDF engines

`PdfRendererInterface::renderPdf(string $html): string` returns bytes. `new DomPdfRenderer(array $options = [])` supports paper (default A4), orientation (default portrait), remote_enabled (default false). It disables embedded PHP. Remote assets should only be enabled for trusted, reviewed templates/destinations; treat HTML as trusted application output and do not promise arbitrary HTML sandboxing.

```php
use MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer;

$bytes = (new DomPdfRenderer(['paper' => 'A4', 'orientation' => 'landscape']))
    ->renderPdf('<h1>Inspection report</h1>');
return response($bytes, 200, ['Content-Type' => 'application/pdf']);
```

WkHtmlToPdfRenderer uses Knp Snappy and an external wkhtmltopdf binary. Path order: options wkhtml_path, WKHTMLTOPDF_PATH env, then wkhtmltopdf in PATH. It disables local-file access and JavaScript; paper maps to page-size and orientation is capitalized for the engine. Install a compatible binary/system dependencies yourself; serverless deployments need a compatible packaged runtime. Missing executables/render errors propagate. Merely installing the Composer package does not install the binary.

Set pdf_renderer=wkhtml plus pdf_options as needed. For a custom implementation bind `docsign.pdf_renderer.NAME` and select NAME. Direct renderer calls are useful for Laravel Fields' optional Pdf adapter: Fields produces a redacted escaped report and reuses this interface without creating Document records.

## Documents, storage and failure recovery

Document fillable fields are title, file_path, status, data, signature_provider and signers; data/signers cast to arrays. The documents migration provides a bigint ID and timestamps. There is no owner/workspace relation, template-version column, provider request-ID contract or authorization scope. Add host relations/columns or a separate domain record and resolve a Document through that ownership before rendering/downloading/signing.

file_path is the disk URL returned at generation time, not a portable storage key. The selected disk's url() behavior determines the result; private S3/local records need a deliberate authorized-download/storage-key mapping. Do not expose a public URL merely because the generated content is confidential. The default API does not return a key/disk pair or automatically create temporary private URLs.

Each regeneration writes a new UUID-named object and sets status back to draft. It does not remove an older file, prohibit regeneration after signing, or make an immutable revision snapshot. Gate allowed document status/revision in your service. A storage write followed by a failed database save can leave an orphan; a database transaction cannot roll back filesystem writes. Implement staging/cleanup/reconciliation around your application's storage lifecycle. Model saves are not guaranteed to elevate an observer's false return to exceptions here, so verify required persisted changes when observers are part of the host. Use a durable job/request reference for provider/file operations that need recovery.

## Signature requests and callbacks

`DocSign::requestSignature(Document $document, array $signers): array` resolves signature.default from signature.providers, calls its createSignatureRequest, and only then saves signature_provider/signers/status=signing. Result array shape belongs to the provider. The manager does not impose a signer schema, authorize the document, validate all addresses, implement request idempotency or persist arbitrary external request metadata. An undefined provider key raises InvalidArgumentException; unresolved/mismatched custom implementations fail through container/type checks. A provider exception leaves the manager from marking the document signing, but an external provider may still have accepted a request before its transport failed; reconcile using provider idempotency/status in your adapter.

Both built-in external providers throw LogicException for create/callback and change no document. API keys in config do not turn them into implemented services. Use a verified custom SignatureProviderInterface:

```php
interface SignatureProviderInterface
{
    public function createSignatureRequest(Document $document, array $signers): array;
    public function processCallback(\Illuminate\Http\Request $request): void;
}
```

Add `signature.providers.acme.class = App\Signing\AcmeProvider::class` and set signature.default=acme. The class is container-resolved. Its adapter must authenticate its provider's webhook, validate document/request ownership, map statuses, handle duplicates/out-of-order callbacks and persist provider identifiers through a host-reviewed schema. Flow/Workflow cannot substitute for authenticating a signing provider.

`DocSign::handleCallback(string $providerKey, Request $request): void` resolves the selected adapter and delegates. If callbacks.enabled is true, the provider registers POST `/docsign/callback/{provider}`, name docsign.callback, using callbacks.middleware (default []). RouteCallbacks::signatureCallback returns JSON `{"status":"callback processed"}` after a successful adapter call. The route does not add universal provider authentication: that belongs to each real adapter/middleware. Do not use the demo's signed-URL mechanism as a claimed implementation of an external provider's webhook verification.

### Local demonstration

Enable both settings and configure middleware appropriate to the demo environment:

```php
return [
'callbacks' => ['enabled' => true, 'middleware' => []],
'signature' => [
    'default' => 'local',
    'providers' => [
        'local' => ['enabled' => true,
            'class' => \MrNewport\LaravelDocSign\Services\Signature\LocalSignatureProvider::class],
    ],
],
];
```

Merge this into the full config rather than deleting other desired settings. Route registration happens at application boot; rebuild route/config caches after changing callback configuration. Call requestSignature on a saved document. It returns an expiring 15-minute signed route URL containing doc_id and an explanatory info string. Submit **POST** to that exact URL; GET does not complete it. Local processing requires a valid unexpired signature (403 otherwise), finds doc_id (404 if absent), and requires provider=local plus status=signing (409 otherwise), then sets completed. Repeated callbacks fail 409 rather than claiming another signature. Both enable switches are checked on request/callback; disabled usage raises LogicException. This demonstrates a state change only.

The per-provider callback_url entries in config are available metadata; the local implementation builds its named route and the generic manager does not automatically send those entries to a provider.

## Configuration, upgrades and verification

Configuration keys: callbacks.enabled/middleware; pdf_renderer/pdf_options; template_engine; signature.default/providers (class and adapter-specific options); storage_disk. Publish with docsign-config. Views can be overridden under resources/views/vendor/docsign; there is no dedicated views publishing tag or package translation namespace. Translate your trusted templates in the host.

Before adopting/upgrading verify saved URLs/disks, status/signers/data, private ownership, old callback consumers, template compatibility, regeneration rules and orphan cleanup on copied data. Callback defaults are off; unsigned local callbacks are refused. Migrate forward with a database backup rather than dropping documents. Do not infer a production signing integration from an adapter class name.

Run `composer test`. Existing tests render actual Dompdf bytes, Blade/Twig templates, generation storage, provider failures and local signed callbacks. wkhtml integration is conditional on its binary; skipped output is not runtime verification. [RECIPES.md](RECIPES.md) shows reviewed agreements, Fields reports and provider integration boundaries; [FEATURES.md](FEATURES.md) lists every public method.
