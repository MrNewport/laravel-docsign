<?php

namespace MrNewport\LaravelDocSign\Providers;

use Illuminate\Support\ServiceProvider;
use MrNewport\LaravelDocSign\Services\DocSignManager;
use Illuminate\Support\Facades\Route;

class DocSignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/docsign.php',
            'docsign'
        );

        $this->app->singleton('docsign.manager', function ($app) {
            return new DocSignManager($app);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/docsign.php' => config_path('docsign.php'),
        ], 'docsign-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Route::group(['namespace' => 'MrNewport\LaravelDocSign\Services'], function () {
            Route::post('/docsign/callback/{provider}', 'RouteCallbacks@signatureCallback')
                ->name('docsign.callback');
        });

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'docsign');
    }
}
