<?php

use Illuminate\Support\Facades\Process;
use Native\Desktop\Builder\Concerns\LocatesPhpBinary;
use Native\Desktop\Builder\Concerns\PrunesVendorDirectory;
use Symfony\Component\Filesystem\Filesystem;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
$buildPath = testsDir('_test_build_path');

beforeEach(function () use ($buildPath) {
    Process::fake();

    $filesystem = new Filesystem;
    $filesystem->remove($buildPath);
});

afterEach(function () use ($buildPath) {
    $filesystem = new Filesystem;
    $filesystem->remove($buildPath);
});

/*
|--------------------------------------------------------------------------
| Mock Build command with anonymous class
|--------------------------------------------------------------------------
*/
$command = new class($buildPath)
{
    use LocatesPhpBinary;
    use PrunesVendorDirectory;

    public function __construct(
        public $buildPath
    ) {}

    public function sourcePath(string $path = ''): string
    {
        return app()->joinPaths($this->buildPath, $path);
    }

    public function buildPath(string $path = ''): string
    {
        return app()->joinPaths($this->buildPath, $path);
    }
};

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/
it('removes the custom php binary package directory', function () use ($buildPath, $command) {
    putenv('NATIVEPHP_PHP_BINARY_PATH=php-bin/');

    createFiles([
        "$buildPath/app/index.php",
        "$buildPath/app/php-bin/bin/php",
    ]);

    $command->pruneVendorDirectory();

    expect("$buildPath/app/php-bin")->not->toBeDirectory();
    expect("$buildPath/app/index.php")->toBeFile();
})->after(fn () => putenv('NATIVEPHP_PHP_BINARY_PATH'));

it('does not delete the app when the binary path normalizes to the app root', function () use ($buildPath, $command) {
    putenv('NATIVEPHP_PHP_BINARY_PATH=./');

    createFiles([
        "$buildPath/app/index.php",
        "$buildPath/app/bin/win/x64/php.exe",
    ]);

    $command->pruneVendorDirectory();

    expect("$buildPath/app/index.php")->toBeFile();
    expect("$buildPath/app/bin/win/x64/php.exe")->toBeFile();
})->after(fn () => putenv('NATIVEPHP_PHP_BINARY_PATH'));
