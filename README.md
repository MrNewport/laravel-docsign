# Laravel Document Generation & E-Signatures (mrnewport/laravel-docsign)

A **config-driven**, **expandable** package for **document generation** (multiple PDF and template engines) and **e-signatures** (multiple providers). Ideal for any use case—from real estate lease agreements, NDAs, or HR forms, to finance, legal, and more.

## Table of Contents
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
  - [PDF Renderer](#pdf-renderer)
  - [Template Engine](#template-engine)
  - [Signature Providers](#signature-providers)
  - [Storage Disk](#storage-disk)
- [Usage](#usage)
  - [Creating a Document](#creating-a-document)
  - [Generating a PDF](#generating-a-pdf)
  - [Requesting E-Signatures](#requesting-e-signatures)
  - [Handling Callbacks](#handling-callbacks)
  - [Example Multi-Signer Flow](#example-multi-signer-flow)
- [Advanced & Unusual Use Cases](#advanced--unusual-use-cases)
  - [Versioning Documents](#versioning-documents)
  - [Security & Encryption](#security--encryption)
  - [Docker Environments](#docker-environments)
  - [Notifications & Webhooks](#notifications--webhooks)
- [Expandability](#expandability)
  - [Custom PDF Engines](#custom-pdf-engines)
  - [Custom Template Engines](#custom-template-engines)
  - [Custom Signature Providers](#custom-signature-providers)
- [Testing](#testing)
- [License](#license)

---

## Features

1. **Multi-Engine PDFs**: DomPDF or wkhtmltopdf (plus custom).
2. **Multi-Engine Templates**: Blade or Twig (plus custom).
3. **Pluggable E-Sign**: Local (demo), DocuSign, HelloSign.
4. **Config-Driven**: Swap engines and providers in `docsign.php` without editing package code.
5. **Storage Disk** support for PDF files.
6. **Facade**-based usage: `DocSign::generate(...)`, `DocSign::requestSignature(...)`, etc.

---

## Requirements

- **PHP** `>=8.1`
- **Laravel** `^11.0`
- If using **wkhtmltopdf**, install the binary on your server/CI environment.

---

## Installation

1. **Require the package**:
   ```bash
   composer require mrnewport/laravel-docsign
   ```

2. **Publish config** (optional):
   ```bash
   php artisan vendor:publish --provider="MrNewport\LaravelDocSign\Providers\DocSignServiceProvider" --tag=docsign-config
   ```

3. **Migrate**:
   ```bash
   php artisan migrate
   ```

**Done**—you can now generate docs, request signatures, handle callbacks.

---

## Configuration

All config is in **`src/config/docsign.php`** (published to `config/docsign.php` if you run the above publish command). **No** direct package edits required—just tweak config to your needs.

### PDF Renderer

```php
'pdf_renderer' => 'dompdf', // or 'wkhtml' or a custom key
'pdf_options' => [
  'paper' => 'A4',
  'orientation' => 'portrait'
],
```
- `'dompdf'` uses a pure-PHP library.
- `'wkhtml'` uses `wkhtmltopdf` (system binary).
- For custom, see [Custom PDF Engines](#custom-pdf-engines).

### Template Engine

```php
'template_engine' => 'blade', // or 'twig' or a custom key
```
- `'blade'` integrates standard Laravel Blade.
- `'twig'` uses Twig.
- For your own engine, see [Custom Template Engines](#custom-template-engines).

### Signature Providers

```php
'signature' => [
  'default' => 'local',
  'providers' => [
    'local' => [...],
    'docusign' => [...],
    'hellosign' => [...]
  ],
],
```
- `'local'` is a demo.
- `'docusign'`, `'hellosign'` are stubs for real external e-sign flows.
- Provide `'api_key'` or `'callback_url'` as needed.

### Storage Disk

```php
'storage_disk' => 'local'
```
Defines which disk (from `config/filesystems.php`) to store final PDFs. E.g., `'s3'` or `'local'`.

---

## Usage

### Creating a Document

```php
use MrNewport\LaravelDocSign\Models\Document;

$document = Document::create([
    'title' => 'NDA Example',
    'data' => [
      '_template' => 'docsign::nda', 
      'partyA' => 'Company X',
      'partyB' => 'John Doe'
    ]
]);
```
- `title` is used for naming the PDF.
- `data` merges into the template, storing placeholders like `'partyA'`.

### Generating a PDF

```php
use MrNewport\LaravelDocSign\Facades\DocSign;

$path = DocSign::generate($document);
// merges template -> renders PDF -> saves to disk -> sets doc.status='draft'
```

### Requesting E-Signatures

```php
$signers = [
  ['name'=>'John','email'=>'john@example.com'],
  ['name'=>'Jane','email'=>'jane@example.com']
];

$result = DocSign::requestSignature($document, $signers);
// sets doc.status='signing', returns array e.g. ['url'=>'...']
```
You might redirect the user to `$result['url']` if it’s an external signature page.

### Handling Callbacks

1. In config, each provider has a `'callback_url'`.
2. The package routes `POST /docsign/callback/{provider}` to `RouteCallbacks@signatureCallback`.
3. That calls `DocSign::handleCallback($provider, $request)`.
4. The provider then sets doc.status='completed' (or similar).

### Example Multi-Signer Flow

If you need signers in a specific **order** or multiple separate sign events:
- Your own application logic can track each signer’s completion.
- Possibly re-call `requestSignature(...)` with the next signer or pass an array with `'order'` keys.
- The package’s built-in providers are minimal stubs; advanced signers with multi-step flows are possible if you create a custom provider (or expand the existing ones).

---

## Advanced & Unusual Use Cases

### Versioning Documents

If you want doc versioning:
1. **Add** a `version` column to `documents`.
2. **Before** re-generating, increment doc.version in your application logic.
3. Store old PDFs under a versioned filename.

### Security & Encryption

For truly sensitive docs:
- You can implement encryption at rest using a custom disk driver (e.g., S3’s server-side encryption).
- If you need password-protected PDFs, certain renderers or PDF post-processing can do that.

### Docker Environments

If using **wkhtmltopdf** in Docker:
1. Add `RUN apt-get update && apt-get install -y wkhtmltopdf` (or a specialized image).
2. Possibly store the path in `.env` as `WKHTMLTOPDF_PATH=/usr/bin/wkhtmltopdf`.
3. The package’s test can skip if `wkhtmltopdf` isn’t found.

### Notifications & Webhooks

Your application can:
- Listen for `'docsign.callback'` route and then send emails or Slack messages.
- Fire your own events upon doc creation, signature requested, or completion.

---

## Expandability

### Custom PDF Engines

1. Create a class implementing `MrNewport\LaravelDocSign\Services\Pdf\PdfRendererInterface`.
2. **Bind** it in your app:
   ```php
   $this->app->bind('docsign.pdf_renderer.mycustom', function($app){
       return new \App\Pdf\MyCustomRenderer();
   });
   ```
3. Set `'pdf_renderer' => 'mycustom'` in `docsign.php`.

### Custom Template Engines

1. Implement `MrNewport\LaravelDocSign\Services\Template\TemplateEngineInterface`.
2. Bind in your **AppServiceProvider**:
   ```php
   $this->app->bind('docsign.template_engine.markdown', function($app){
       return new \App\Template\MarkdownEngine();
   });
   ```
3. `'template_engine' => 'markdown'`.

### Custom Signature Providers

1. Implement `SignatureProviderInterface`.
2. Add it to `config('docsign.signature.providers')`.
3. **No** package edits. If `'key' => 'mysigner'`, then `'class' => \App\Signing\MySignerProvider::class`.

---

## Testing

```bash
composer test
```
- **DocumentTest**: verifies doc creation & PDF generation.
- **SignatureTest**: checks requestSignature + local callback.
- **PdfTest**: tests DomPDF or Wkhtml if installed.
- **TemplateTest**: tests Blade & Twig.

In CI or local dev, ensure you have **wkhtmltopdf** installed if you want that test to pass. DomPDF-based tests do not require extra binaries.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). Expand it solely via **config** or **custom classes**—**never** by editing the package’s internal files. Enjoy your dynamic documents and e-sign workflows!
