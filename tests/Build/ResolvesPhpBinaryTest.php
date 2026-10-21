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

$cacheRoot = testsDir('_test_bin_cache');

beforeEach(function () use ($cacheRoot) {
    (new Filesystem)->remove($cacheRoot);

    // Point the per-user cache at a disposable test directory and select R2.
    putenv("NATIVEPHP_BIN_CACHE_PATH={$cacheRoot}");
    putenv('NATIVEPHP_BIN_SOURCE=r2');
    putenv('NATIVEPHP_BIN_BRANCH=main');
    $_ENV['NATIVEPHP_BIN_CACHE_PATH'] = $cacheRoot;
    $_ENV['NATIVEPHP_BIN_SOURCE'] = 'r2';
    $_ENV['NATIVEPHP_BIN_BRANCH'] = 'main';
});

afterEach(function () use ($cacheRoot) {
    (new Filesystem)->remove($cacheRoot);

    foreach (['NATIVEPHP_BIN_CACHE_PATH', 'NATIVEPHP_BIN_SOURCE', 'NATIVEPHP_BIN_BRANCH'] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/

it('reuses a cached zip whose sha256 matches the manifest without downloading it again', function () use ($cacheRoot) {
    $zipBytes = fakeZipBytes();
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
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

    expect($returned)->toBe($cacheRoot);
    Http::assertSentCount(1);
    Http::assertNotSent(fn ($request) => $request->url() === $zipUrl);
});

it('replaces a cached zip once the manifest points at a newer build', function () use ($cacheRoot) {
    $oldBytes = fakeZipBytes('php', 'php-8.3.35');
    $newBytes = fakeZipBytes('php', 'php-8.3.36');
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
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

it('keeps the cached zip when a newer build fails to download', function () use ($cacheRoot) {
    $oldBytes = fakeZipBytes('php', 'php-8.3.35');
    $zipUrl = 'https://r2.example.com/mac/arm64/php-8.3.zip';

    $cachedPath = Path::join($cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
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

    expect($returned)->toBe($cacheRoot)
        ->and(hash_file('sha256', $cachedPath))->toBe(hash('sha256', $oldBytes))
        ->and(glob(Path::join($cacheRoot, 'mac', 'arm64', '*.part')))->toBe([]);
});

it('uses a cached zip when the manifest cannot be fetched', function () use ($cacheRoot) {
    $cachedPath = Path::join($cacheRoot, 'mac', 'arm64', 'php-8.3.zip');
    (new Filesystem)->dumpFile($cachedPath, fakeZipBytes());

    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 404),
    ]);

    expect((new PhpBinaryDownloader)->ensure('mac', 'arm64', '8.3'))->toBe($cacheRoot);
});

it('fetches the manifest once when prefetching several build targets', function () use ($cacheRoot) {
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

    expect($path)->toBe($cacheRoot)
        ->and($builder->phpBinaryPath())->toBe($cacheRoot)
        ->and(Path::join($cacheRoot, 'mac', 'x64', 'php-8.4.zip'))->toBeFile()
        ->and(Path::join($cacheRoot, 'mac', 'arm64', 'php-8.4.zip'))->toBeFile();

    Http::assertSentCount(3);
});

it('falls back to the bundled binaries when R2 has nothing usable', function () {
    Http::fake([
        'bin.nativephp.com/*' => Http::response('', 404),
    ]);

    $builder = resolve(Builder::class);

    expect($builder->usesR2BinarySource())->toBeTrue()
        ->and($builder->phpBinaryPath())->toEndWith('vendor/nativephp/php-bin/bin');
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

it('fetches the manifest and selects the correct {os}-{arch}/version entry, then verifies sha256', function () use ($cacheRoot) {
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

    $cachedPath = Path::join($cacheRoot, $os, $arch, "php-{$version}.zip");

    expect($returned)->toBe($cacheRoot)
        ->and($cachedPath)->toBeFile()
        ->and(hash_file('sha256', $cachedPath))->toBe($sha);

    // It hit the manifest and the correct zip url.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'versions.json'));
    Http::assertSent(fn ($request) => $request->url() === $zipUrl);
    // It never touched the wrong-platform url.
    Http::assertNotSent(fn ($request) => $request->url() === $wrongUrl);
});

it('validates a bare-url entry (no sha256) as a real zip via ZipArchive', function () use ($cacheRoot) {
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

    $cachedPath = Path::join($cacheRoot, $os, $arch, "php-{$version}.zip");

    expect($returned)->toBe($cacheRoot)
        ->and($cachedPath)->toBeFile();
});

it('rejects a sha256 mismatch and writes nothing to the cache', function () use ($cacheRoot) {
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

    $cachedPath = Path::join($cacheRoot, $os, $arch, "php-{$version}.zip");

    // Nothing should be left in the cache: no final file and no temp .part files.
    expect($cachedPath)->not->toBeFile();

    $leftovers = glob(Path::join($cacheRoot, $os, $arch, '*'));
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

it('routes Builder::phpBinaryPath() through the R2 resolver when NATIVEPHP_BIN_SOURCE=r2', function () use ($cacheRoot) {
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

    // The build env var now points at the per-user cache root.
    expect($path)->toBe($cacheRoot)
        ->and(Path::join($cacheRoot, $os, $arch, "php-{$version}.zip"))->toBeFile();
});
