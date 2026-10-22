<?php

namespace Native\Desktop\Tests;

use Native\Desktop\Drivers\Electron\ElectronServiceProvider;
use Native\Desktop\NativeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    // Fixes Testbench compat issue
    public static $latestResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep tests off the network. Tests that cover the R2 resolver opt in.
        putenv('NATIVEPHP_BIN_SOURCE=local');
        $_ENV['NATIVEPHP_BIN_SOURCE'] = 'local';
    }

    protected function getPackageProviders($app)
    {
        return [
            NativeServiceProvider::class,
            ElectronServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
