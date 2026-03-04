<?php

use Native\Desktop\Support\Composer;
use Symfony\Component\Filesystem\Filesystem;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
$testPath = testsDir('_test_composer_scripts');

beforeEach(function () use ($testPath) {
    (new Filesystem)->mkdir($testPath);
    app()->setBasePath($testPath);
});

afterEach(function () use ($testPath) {
    (new Filesystem)->remove($testPath);
});

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/
it('installs native:dev and dev scripts', function () use ($testPath) {
    (new Filesystem)->dumpFile("{$testPath}/composer.json", json_encode([
        'name' => 'test/app',
        'require' => new stdClass,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Composer::installDevScript();

    $composer = json_decode(file_get_contents("{$testPath}/composer.json"));

    expect($composer->scripts->{'native:dev'})
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('Composer\\Config::disableProcessTimeout')
        ->and(str_contains($composer->scripts->{'native:dev'}[1], 'native:run'))
        ->toBeTrue()
        ->and(str_contains($composer->scripts->{'native:dev'}[1], 'npm run dev'))
        ->toBeTrue();

    expect($composer->scripts->dev)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('Composer\\Config::disableProcessTimeout')
        ->and(str_contains($composer->scripts->dev[1], 'php artisan serve'))
        ->toBeTrue()
        ->and(str_contains($composer->scripts->dev[1], 'native:run'))
        ->toBeTrue()
        ->and(str_contains($composer->scripts->dev[1], 'queue:listen'))
        ->toBeTrue()
        ->and(str_contains($composer->scripts->dev[1], 'npm run dev'))
        ->toBeTrue()
        ->and(str_contains($composer->scripts->dev[1], 'server,native,queue,vite'))
        ->toBeTrue();
});

it('skips when native:dev already exists', function () use ($testPath) {
    (new Filesystem)->dumpFile("{$testPath}/composer.json", json_encode([
        'name' => 'test/app',
        'require' => new stdClass,
        'scripts' => [
            'native:dev' => ['existing-script'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Composer::installDevScript();

    $composer = json_decode(file_get_contents("{$testPath}/composer.json"));

    expect($composer->scripts->{'native:dev'})
        ->toBeArray()
        ->toContain('existing-script');
});

it('overwrites existing dev script with full command', function () use ($testPath) {
    (new Filesystem)->dumpFile("{$testPath}/composer.json", json_encode([
        'name' => 'test/app',
        'require' => new stdClass,
        'scripts' => [
            'dev' => ['custom-vite-command'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    Composer::installDevScript();

    $composer = json_decode(file_get_contents("{$testPath}/composer.json"));

    expect($composer->scripts->dev)
        ->toBeArray()
        ->toContain('Composer\\Config::disableProcessTimeout')
        ->and(str_contains($composer->scripts->dev[1], 'native:run'))
        ->toBeTrue();
});
