# Laravel DocSign

Trusted Blade/Twig rendering, PDF engines, persisted Document records and signature-provider contracts. PDF generation works independently; built-in external signing adapters in the published v2.0.0 release explicitly throw, and the local opt-in adapter is a state demonstration.

Current release: **2.0.0**. Supported installations: Laravel 12 (PHP 8.2+) and Laravel 13 (PHP 8.3+). CI verifies supported PHP/Laravel combinations. Earlier Laravel versions should remain on the previous major release.

## Proposed private DocuSign transport

> This branch is a proposed, unpublished successor to v2.0.0. The current published release has no working external signature adapter.

`DocuSignPrivateSignatureProvider` implements `PrivateSignatureProviderInterface` for application-owned immutable PDF bytes. It validates a locally configured 2048-bit or stronger RSA key, uses separate demo and production JWT hosts, verifies a production account and base URI through UserInfo, sends one transaction ID, reconciles an ambiguous result by that ID, verifies raw Connect HMAC bytes, and fetches completed signed PDF and certificate bytes. The application can call `prepare()` before reserving an envelope attempt: it authenticates the account without transmitting the PDF or recipient, then reuses that authorization for `submit`. This lets a known OAuth or consent failure stop before the application's irreversible send fence. A lost response after envelope dispatch remains ambiguous. DocuSign limits transaction-ID lookup to seven days; an application must retain an unresolved attempt after that deadline rather than silently resend. The transport does not decide recipient authority, consent, agreement status, storage, or financial effects. Both mode switches start disabled. An application must retain the exact source and request before calling `submit`, and must independently review provider artifacts before legal or operational activation.

Private signing accepts exactly one RSA source: an absolute local `rsa_private_key_path` or bounded in-memory `rsa_private_key_pem`. Both PKCS#1 and PKCS#8 private keys must be RSA with at least 2048 bits. A caller using an encrypted environment should decode and bind the PEM at runtime; putting it directly in Laravel's cached configuration can write the secret to the config-cache file. Invalid or conflicting sources fail before an HTTP request and errors do not include key material.


```sh
composer require mrnewport/laravel-docsign:^2.0
```

If your Composer registry has not indexed the GitHub tag, add its VCS source and repeat the requirement:

```sh
composer config repositories.laravel-docsign vcs https://github.com/MrNewport/laravel-docsign
composer require mrnewport/laravel-docsign:^2.0
```

Publish `docsign-config`, select the storage/template/PDF behavior, then run `php artisan migrate`. Callbacks default off. A real signing adapter must implement and verify its provider contract; a configured API key alone is insufficient.

Start with the [handbook](docs/HANDBOOK.md) for fresh Laravel 12/13 installation and the complete feature/operational guide. The [feature index](docs/FEATURES.md) links every public API, return contract, failure mode and executable reference. The [recipes](docs/RECIPES.md) show serious standalone and optional cross-package application use.

Laravel's own PHP runtime requirements apply. Package dependency constraints and current test coverage are described in the handbook. Run `composer test` in a development checkout; optional browser/renderer checks are documented with their requirements. Installing this package does not automatically integrate another package or authorize application records.
