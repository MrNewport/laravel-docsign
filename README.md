# Laravel DocSign

Trusted Blade/Twig rendering, PDF engines, persisted Document records and signature-provider contracts. PDF generation works independently; built-in external signing adapters explicitly throw, and the local opt-in adapter is a state demonstration.

**[Interactive workshop](https://matthewnewport.com/packages/laravel-docsign/lab)** — Try guided examples in your browser.

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
