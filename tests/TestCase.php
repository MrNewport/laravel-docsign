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
