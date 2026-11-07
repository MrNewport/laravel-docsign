# Laravel Docsign

> The private DocuSign transport described below is a proposed, unpublished successor to v2.0.0. The current published release still has no working external signature adapter.

## Proposed private DocuSign transport

`DocuSignPrivateSignatureProvider` implements `PrivateSignatureProviderInterface` for application-owned immutable PDF bytes. It validates a locally configured 2048-bit or stronger RSA key, uses separate demo and production JWT hosts, verifies a production account and base URI through UserInfo, sends one transaction ID, reconciles an ambiguous result by that ID, verifies raw Connect HMAC bytes, and fetches completed signed PDF and certificate bytes. The application can call `prepare()` before reserving an envelope attempt: it authenticates the account without transmitting the PDF or recipient, then reuses that authorization for `submit`. This lets a known OAuth or consent failure stop before the application's irreversible send fence. A lost response after envelope dispatch remains ambiguous. DocuSign limits transaction-ID lookup to seven days; an application must retain an unresolved attempt after that deadline rather than silently resend. The transport does not decide recipient authority, consent, agreement status, storage, or financial effects. Both mode switches start disabled. An application must retain the exact source and request before calling `submit`, and must independently review provider artifacts before legal or operational activation.

Private signing accepts exactly one RSA source: an absolute local `rsa_private_key_path` or bounded in-memory `rsa_private_key_pem`. Both PKCS#1 and PKCS#8 private keys must be RSA with at least 2048 bits. A caller using an encrypted environment should decode and bind the PEM at runtime; putting it directly in Laravel's cached configuration can write the secret to the config-cache file. Invalid or conflicting sources fail before an HTTP request and errors do not include key material.

Current release: **2.0.0**. Supported installations: Laravel 12 (PHP 8.2+) and Laravel 13 (PHP 8.3+). CI verifies supported PHP/Laravel combinations. Earlier Laravel versions should remain on the previous major release.

Laravel 12/13; Dompdf 3.1.6+; testing dependencies removed from production. Fixed the HelloSign class/autoload collision, supplied the default template, disabled remote PDF assets and local-file access by default, used UUID storage names and checked write failures. Built-in external signature adapters now explicitly reject unsupported operations. Local demonstrations and callbacks require opt-in; callbacks require expiring signed URLs and a pending local document. Failed provider requests no longer mark documents as signing.

```sh
composer require mrnewport/laravel-docsign:^2.0
composer test # from the package checkout; tests use isolated fixtures
```

GitHub source and tags are published first. Until the release is indexed on Packagist, add this repository as a Composer VCS repository. Never install test dependencies in your production application's require section.

Run `php artisan migrate` after upgrading to apply package migrations. Back up application data before normal production migrations.

## Signature integration status

PDF generation with Dompdf and Blade/Twig is implemented and tested. The optional wkhtmltopdf renderer requires a separately installed system binary. Built-in DocuSign and HelloSign adapters are reserved extension points, **not working external integrations**. They throw before changing a document. Supply your own `SignatureProviderInterface` implementation and verify webhook authenticity inside `processCallback` before changing any state.

For a local demonstration only, enable `docsign.callbacks.enabled` and `docsign.signature.providers.local.enabled`. POST to the expiring signed URL returned by `requestSignature`. This demonstrates document state transitions; it does not collect or verify a person's signature. No callback route is exposed by default. Configure `docsign.callbacks.middleware` for any application-specific callback requirements.

Upgrade from 1.x: callbacks now default off; unsigned requests are rejected. Explicitly supply a verified adapter for external providers. Dompdf remote assets default off (`pdf_options.remote_enabled` can enable them for trusted templates). Use only trusted application-controlled templates. Uploaded arbitrary Blade/Twig is not sandboxed.

## Generate a PDF

```php
use MrNewport\LaravelDocSign\Models\Document;
use MrNewport\LaravelDocSign\Facades\DocSign;

$document = Document::create([
    'title' => 'Agreement',
    'data' => ['_template' => 'agreements.lease', 'tenant' => 'Example'],
]);
$url = DocSign::generate($document);
```

Publish configuration with `php artisan vendor:publish --tag=docsign-config`. Use `template_engine=blade` with a trusted application view in `_template`, or `twig` with `_template_str`. The default Blade template accepts `title` and `content`. Generated PDFs are stored on `docsign.storage_disk`; the returned URL and `Document::file_path` use that disk's URL behavior. Private downloads must be authorized by the application.

Implement `PdfRendererInterface`, `TemplateEngineInterface`, or `SignatureProviderInterface` to extend the package. Bind custom PDF/template engines as `docsign.pdf_renderer.NAME` / `docsign.template_engine.NAME`; signature providers are configured with their container-resolvable `class`. These extension contracts do not supply webhook authentication: each real signature adapter must verify its provider's callback before changing state.

Dompdf is tested with actual PDF output. `pdf_renderer=wkhtml` requires wkhtmltopdf separately; configure `pdf_options.wkhtml_path`. Its optional integration test is skipped when that binary is unavailable. This renderer is not suitable for Vapor without a compatible binary in your deployment runtime.
