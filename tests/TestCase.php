<?php

namespace MrNewport\LaravelDocSign\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MrNewport\LaravelDocSign\Providers\DocSignServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            DocSignServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
        $app['config']->set('docsign.callbacks.enabled', true);
        $app['config']->set('docsign.signature.providers.local.enabled', true);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database'=>'testing'])->run();
    }
}
