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
    putenv('NATIVEPHP_PHP_BINARY_PATH');

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
it('prunes the bundled php binary archives from a custom binary directory', function () use ($buildPath, $command) {
    putenv('NATIVEPHP_PHP_BINARY_PATH=php-bin/');

    createFiles([
        "$buildPath/app/index.php",
        "$buildPath/app/php-bin/bin/win/x64/php-8.4.zip",
        "$buildPath/app/php-bin/bin/mac/arm64/php-8.4.zip",
    ]);

    $command->pruneVendorDirectory();

    // The redundant archives are stripped...
    expect("$buildPath/app/php-bin/bin/win/x64/php-8.4.zip")->not->toBeFile();
    expect("$buildPath/app/php-bin/bin/mac/arm64/php-8.4.zip")->not->toBeFile();

    // ...while the rest of the app is left intact.
    expect("$buildPath/app/index.php")->toBeFile();
});

it('does not delete the app when the binary path is the project root', function () use ($buildPath, $command) {
    // Regression test for #115: a binary path that normalises to the app root
    // (e.g. binaries kept directly in the project's bin/ directory) must not
    // take the whole app down with the prune.
    putenv('NATIVEPHP_PHP_BINARY_PATH=./');

    createFiles([
        "$buildPath/app/index.php",
        "$buildPath/app/bin/win/x64/php-8.4.zip",
        "$buildPath/app/bin/mac/arm64/php-8.4.zip",
        // Unrelated tooling a user happens to keep in bin/ must survive.
        "$buildPath/app/bin/deploy.sh",
    ]);

    $command->pruneVendorDirectory();

    // The app and any non-NativePHP files in bin/ are preserved...
    expect("$buildPath/app/index.php")->toBeFile();
    expect("$buildPath/app/bin/deploy.sh")->toBeFile();

    // ...but the bundled php archives are still pruned.
    expect("$buildPath/app/bin/win/x64/php-8.4.zip")->not->toBeFile();
    expect("$buildPath/app/bin/mac/arm64/php-8.4.zip")->not->toBeFile();
});
