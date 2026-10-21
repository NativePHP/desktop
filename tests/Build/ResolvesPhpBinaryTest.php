<?php

use Illuminate\Support\Facades\Http;
use Native\Desktop\Builder\Builder;
use Native\Desktop\Builder\PhpBinaryDownloader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/*
|--------------------------------------------------------------------------
| Helpers / fixtures
|--------------------------------------------------------------------------
*/

/**
 * Build a real (tiny) zip archive in memory and return its raw bytes.
 */
function fakeZipBytes(string $entryName = 'php', string $contents = 'fake-php-binary'): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'nativephp_zip_');

    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString($entryName, $contents);
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);

    return $bytes;
}

beforeEach(function () {
    $this->cacheRoot = Path::join(base_path('nativephp/binaries'), 'main');
    (new Filesystem)->remove(base_path('nativephp/binaries'));

    putenv('NATIVEPHP_BIN_SOURCE=r2');
    putenv('NATIVEPHP_BIN_BRANCH=main');
    $_ENV['NATIVEPHP_BIN_SOURCE'] = 'r2';
    $_ENV['NATIVEPHP_BIN_BRANCH'] = 'main';
});

afterEach(function () {
    (new Filesystem)->remove(base_path('nativephp/binaries'));
    @rmdir(base_path('nativephp'));

    foreach (['NATIVEPHP_BIN_SOURCE', 'NATIVEPHP_BIN_BRANCH'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/

it('reuses a cached zip whose sha256 matches the manifest without downloading it again', function () {
    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($this->cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
    (new Filesystem)->dumpFile($cachedPath, $zipBytes);

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.3' => ['mac-arm64' => [['url' => $zipUrl, 'sha256' => hash('sha256', $zipBytes)]]],
            ],
        ]),
        '*' => Http::response('SHOULD NOT BE CALLED', 500),
    ]);

    $returned = (new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.3');

    expect($returned)->toBe($this->cacheRoot);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->url() === $zipUrl);
});

it('replaces a cached zip once the manifest points at a newer build', function () {
    $oldBytes = fakeZipBytes('php', 'php-8.3.35');
    $newBytes = fakeZipBytes('php', 'php-8.3.36');
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($this->cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
    (new Filesystem)->dumpFile($cachedPath, $oldBytes);

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.3' => ['mac-arm64' => [['url' => $zipUrl, 'sha256' => hash('sha256', $newBytes), 'size' => strlen($newBytes)]]],
            ],
        ]),
        $zipUrl => Http::response($newBytes, 200),
    ]);

    (new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.3');

    expect(hash_file('sha256', $cachedPath))->toBe(hash('sha256', $newBytes));
    Http::assertSent(fn ($request) => $request->url() === $zipUrl);
});

it('keeps the cached zip when a newer build fails to download', function () {
    $oldBytes = fakeZipBytes('php', 'php-8.3.35');
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($this->cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
    (new Filesystem)->dumpFile($cachedPath, $oldBytes);

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.3' => ['mac-arm64' => [['url' => $zipUrl, 'sha256' => str_repeat('b', 64)]]],
            ],
        ]),
        $zipUrl => Http::response('', 503),
    ]);

    $returned = (new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.3');

    expect($returned)->toBe($this->cacheRoot)
        ->and(hash_file('sha256', $cachedPath))->toBe(hash('sha256', $oldBytes))
        ->and(glob(Path::join($this->cacheRoot, 'mac', 'arm64', '*.part')))->toBe([]);
});

it('uses a cached zip when the manifest cannot be fetched', function () {
    $cachedPath = Path::join($this->cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
    (new Filesystem)->dumpFile($cachedPath, fakeZipBytes());

    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 404),
    ]);

    expect((new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.3'))->toBe($this->cacheRoot);
});

it('fetches the manifest once when prefetching several build targets', function () {
    $zipBytes = fakeZipBytes();
    $sha = hash('sha256', $zipBytes);

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.4' => [
                    'mac-x64' => [['url' => 'https://r2.example.com/mac/x64/php-8.4.zip', 'sha256' => $sha]],
                    'mac-arm64' => [['url' => 'https://r2.example.com/mac/arm64/php-8.4.zip', 'sha256' => $sha]],
                ],
            ],
        ]),
        // A closure, so each download gets a fresh body stream.
        'r2.example.com/*' => fn () => Http::response($zipBytes, 200),
    ]);

    $builder = resolve(Builder::class);
    $path = $builder->prefetchPhpBinariesForBuild('mac', '8.4');

    expect($path)->toBe($this->cacheRoot)
        ->and($builder->phpBinaryPath())->toBe($this->cacheRoot)
        ->and(Path::join($this->cacheRoot, 'mac', 'x64', 'php-8.4.zip'))->toBeFile()
        ->and(Path::join($this->cacheRoot, 'mac', 'arm64', 'php-8.4.zip'))->toBeFile();

    Http::assertSentCount(3);
});

it('falls back to nativephp/php-bin when R2 has nothing usable and php-bin is installed', function () {
    $appPath = testsDir('_test_app');
    (new Filesystem)->mkdir(Path::join($appPath, 'vendor/nativephp/php-bin/bin'));

    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 404),
    ]);

    try {
        $builder = Builder::make(buildPath: testsDir('_test_app_build'), sourcePath: $appPath);

        expect($builder->phpBinaryPath())->toBe(Path::join($appPath, 'vendor/nativephp/php-bin/bin'));
    } finally {
        (new Filesystem)->remove($appPath);
    }
});

it('says how to work offline when R2 has nothing usable and php-bin is not installed', function () {
    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 404),
    ]);

    $builder = Builder::make(buildPath: testsDir('_test_app_build'), sourcePath: testsDir('_test_app'));

    expect(fn () => $builder->phpBinaryPath())
        ->toThrow(RuntimeException::class, 'composer require nativephp/php-bin');
});

it('uses R2 by default unless the app points at its own binaries', function () {
    putenv('NATIVEPHP_BIN_SOURCE');
    unset($_ENV['NATIVEPHP_BIN_SOURCE'], $_SERVER['NATIVEPHP_BIN_SOURCE']);

    $builder = resolve(Builder::class);

    expect($builder->usesR2BinarySource())->toBeTrue();

    putenv('NATIVEPHP_PHP_BINARY_PATH=php-bin/');
    $_ENV['NATIVEPHP_PHP_BINARY_PATH'] = 'php-bin/';

    try {
        expect($builder->usesR2BinarySource())->toBeFalse();
    } finally {
        putenv('NATIVEPHP_PHP_BINARY_PATH');
        unset($_ENV['NATIVEPHP_PHP_BINARY_PATH']);
    }
});

it('fetches the manifest and selects the correct {os}-{arch}/version entry, then verifies sha256', function () {
    $os = 'linux';
    $arch = 'x64';
    $version = '8.4';

    $zipBytes = fakeZipBytes();
    $sha = hash('sha256', $zipBytes);

    $zipUrl = 'https://r2.example.com/linux/x64/php-8.4.zip';
    $wrongUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.3' => [
                    'mac-arm64' => [['url' => $wrongUrl, 'sha256' => 'deadbeef']],
                ],
                '8.4' => [
                    // The entry we expect to be selected.
                    'linux-x64' => [['url' => $zipUrl, 'sha256' => $sha, 'size' => strlen($zipBytes)]],
                    'mac-arm64' => [['url' => $wrongUrl, 'sha256' => 'deadbeef']],
                ],
            ],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
        '*' => Http::response('SHOULD NOT BE CALLED', 500),
    ]);

    $downloader = new PhpBinaryDownloader;
    $returned = $downloader->ensure($os, $arch, $version);

    $cachedPath = Path::join($this->cacheRoot, $os, $arch, "php-{$version}.zip");

    expect($returned)->toBe($this->cacheRoot)
        ->and($cachedPath)->toBeFile()
        ->and(hash_file('sha256', $cachedPath))->toBe($sha);

    // It hit the manifest and the correct zip url.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'versions.json'));
    Http::assertSent(fn ($request) => $request->url() === $zipUrl);
    // It never touched the wrong-platform url.
    Http::assertNotSent(fn ($request) => $request->url() === $wrongUrl);
});

it('validates a bare-url entry (no sha256) as a real zip via ZipArchive', function () {
    $os = 'mac';
    $arch = 'x64';
    $version = '8.3';

    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/mac/x64/php-8.3.zip';

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                // entry is a plain URL string, not an object
                '8.3' => ['mac-x64' => [$zipUrl]],
            ],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
    ]);

    $downloader = new PhpBinaryDownloader;
    $returned = $downloader->ensure($os, $arch, $version);

    $cachedPath = Path::join($this->cacheRoot, $os, $arch, "php-{$version}.zip");

    expect($returned)->toBe($this->cacheRoot)
        ->and($cachedPath)->toBeFile();
});

it('rejects a sha256 mismatch and writes nothing to the cache', function () {
    $os = 'mac';
    $arch = 'arm64';
    $version = '8.3';

    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                '8.3' => [
                    // sha256 here is deliberately wrong
                    'mac-arm64' => [['url' => $zipUrl, 'sha256' => str_repeat('a', 64)]],
                ],
            ],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
    ]);

    $downloader = new PhpBinaryDownloader;

    expect(fn () => $downloader->ensure($os, $arch, $version))
        ->toThrow(RuntimeException::class);

    $cachedPath = Path::join($this->cacheRoot, $os, $arch, "php-{$version}.zip");

    // Nothing should be left in the cache: no final file and no temp .part files.
    expect($cachedPath)->not->toBeFile();

    $leftovers = glob(Path::join($this->cacheRoot, $os, $arch, '*'));
    expect($leftovers === false ? [] : $leftovers)->toBe([]);
});

it('throws a clear offline error when the manifest cannot be fetched', function () {
    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 503),
    ]);

    $downloader = new PhpBinaryDownloader;

    expect(fn () => $downloader->ensure('mac', 'arm64', '8.3'))
        ->toThrow(RuntimeException::class);
});

it('bypasses the resolver entirely when NATIVEPHP_BIN_SOURCE=local', function () {
    putenv('NATIVEPHP_BIN_SOURCE=local');
    $_ENV['NATIVEPHP_BIN_SOURCE'] = 'local';

    Http::fake([
        '*' => Http::response('SHOULD NOT BE CALLED', 500),
    ]);

    $builder = resolve(Builder::class);

    $path = $builder->phpBinaryPath();

    // Local source => path is the bundled vendor php-bin/bin dir, ends with /bin/.
    expect($builder->usesR2BinarySource())->toBeFalse()
        ->and($path)->toContain('php-bin')
        ->and(str_ends_with($path, 'bin/') || str_ends_with($path, 'bin'))->toBeTrue();

    // The resolver never reached out to the network.
    Http::assertNothingSent();
});

it('routes Builder::phpBinaryPath() through the R2 resolver when NATIVEPHP_BIN_SOURCE=r2', function () {
    $os = match (PHP_OS_FAMILY) {
        'Windows' => 'win',
        'Darwin' => 'mac',
        default => 'linux',
    };
    $arch = str_contains(strtolower(php_uname('m')), 'arm64') || str_contains(strtolower(php_uname('m')), 'aarch64')
        ? 'arm64'
        : 'x64';
    $version = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

    $zipBytes = fakeZipBytes();
    $sha = hash('sha256', $zipBytes);
    $zipUrl = "https://r2.example.com/{$os}/{$arch}/php-{$version}.zip";

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => [
                $version => [
                    "{$os}-{$arch}" => [['url' => $zipUrl, 'sha256' => $sha]],
                ],
            ],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
    ]);

    $builder = resolve(Builder::class);

    expect($builder->usesR2BinarySource())->toBeTrue();

    $path = $builder->phpBinaryPath();

    // The build env var now points at the app's binaries directory.
    expect($path)->toBe($this->cacheRoot)
        ->and(Path::join($this->cacheRoot, $os, $arch, "php-{$version}.zip"))->toBeFile();
});

it('keeps downloads in nativephp/binaries and out of git', function () {
    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.4.zip';

    Http::fake([
        'bin.nativephp.com/main/desktop/versions.json' => Http::response([
            'versions' => ['8.4' => ['mac-arm64' => [['url' => $zipUrl, 'sha256' => hash('sha256', $zipBytes)]]]],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
    ]);

    (new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.4');

    expect(base_path('nativephp/binaries/main/mac/arm64/php-8.4.zip'))->toBeFile()
        ->and(file_get_contents(base_path('nativephp/binaries/.gitignore')))->toBe('*'.PHP_EOL);
});

it('keeps each branch in its own directory', function () {
    putenv('NATIVEPHP_BIN_BRANCH=feat/new-php/');
    $_ENV['NATIVEPHP_BIN_BRANCH'] = 'feat/new-php/';

    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/feat/mac/arm64/php-8.4.zip';

    Http::fake([
        'bin.nativephp.com/feat/new-php/desktop/versions.json' => Http::response([
            'versions' => ['8.4' => ['mac-arm64' => [['url' => $zipUrl, 'sha256' => hash('sha256', $zipBytes)]]]],
        ]),
        $zipUrl => Http::response($zipBytes, 200),
    ]);

    $returned = (new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.4');

    expect($returned)->toBe(Path::join(base_path('nativephp/binaries'), 'feat-new-php'))
        ->and(Path::join($returned, 'mac/arm64/php-8.4.zip'))->toBeFile();
});
