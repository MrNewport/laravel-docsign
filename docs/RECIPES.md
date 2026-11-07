# DocSign recipes

## Reviewed agreement with immutable evidence

Authorize the business record first. Capture an application-owned revision containing the approved terms, tenant/customer identity and trusted template version. Create a Document whose data contains that snapshot and a fixed application view name; generate the PDF using DocSign. Bind its ID to the owner/revision in your domain record. Store/download through your deliberate private-storage contract because Document.file_path is a disk URL, not a storage-key DTO.

```php
$document = Document::create([
    'title' => 'Inspection agreement',
    'data' => ['_template' => 'documents.inspection',
        'customer_name' => $revision->customer_name,
        'terms' => $revision->terms],
]);
$url = DocSign::generate($document);
```

The host supplies the template and revision model. Do not regenerate a signed revision in place; create a new reviewed version and preserve the old evidence/file. Track storage/provider operations with durable host job IDs and reconcile failures after a file write or external request, rather than assuming a database rollback undoes them.

## Fields report without document/signing state

```php
use MrNewport\LaravelFields\{Field, Schema};
use MrNewport\LaravelFields\Adapters\Pdf;
use MrNewport\LaravelDocSign\Services\Pdf\DomPdfRenderer;

$schema = Schema::make([
    Field::text('name')->label('Customer'),
    Field::decimal('approved_limit', 2),
    Field::password('password'),
]);
$bytes = (new Pdf(new DomPdfRenderer))->export(
    $schema, ['name' => 'Avery', 'approved_limit' => '150.00'], 'Approval report'
);
```

Fields is optional and installed separately. Its adapter redacts sensitive/file fields, creates escaped HTML and calls the DocSign renderer contract. No Document/signature request is created. Authorize the report record and deliver bytes with Content-Type application/pdf through the host. Standalone DocSign remains usable without Fields.

## External provider implementation checklist expressed as contracts

Implement SignatureProviderInterface in an application adapter. createSignatureRequest validates approved signers, uses a retained provider idempotency reference, submits the exact approved PDF and persists the request association in a host-owned record. processCallback authenticates provider signatures against the raw request, checks provider/request/document binding, rejects stale/invalid transitions, records replay IDs and updates domain/document state atomically where possible. Reconcile timeouts with the provider's status API before issuing a second request.

Configure the adapter class under a new signature.providers key. Add only the provider's required callback middleware. The shipped DocuSign/HelloSign classes intentionally throw; do not present them as ready services. Test authentic/forged/expired/repeated/out-of-order callbacks and uncertain request success against that provider's actual contract before exposing the integration.

## Local callback tutorial

Enable both local demonstration and callbacks in a development application, rebuild caches, create/generate a saved document, then requestSignature. Present the returned url as a POST form action with no modified query parameters. It expires in 15 minutes and requires signing/local state. Verify valid POST completes, GET does not, expired/tampered links fail, and replay returns 409. This demonstrates application state transitions and does not collect a signature. The [signature tests](../tests/Feature/SignatureTest.php) execute those boundaries.
