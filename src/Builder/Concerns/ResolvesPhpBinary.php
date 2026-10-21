<?php

namespace Native\Desktop\Builder\Concerns;

use Native\Desktop\Builder\PhpBinaryDownloader;
use RuntimeException;

use function Laravel\Prompts\warning;

/**
 * Download resolver for NativePHP desktop PHP binaries.
 *
 * Controlled by NATIVEPHP_BIN_SOURCE:
 *   - "r2" (default): resolve the binary from Cloudflare R2 (via the
 *     versions.json manifest) into a per-user cache and point
 *     NATIVEPHP_PHP_BINARY_PATH there.
 *   - "local": use the binaries from nativephp/php-bin (which apps have to
 *     require themselves), or the custom NATIVEPHP_PHP_BINARY_PATH.
 *
 * When NATIVEPHP_BIN_SOURCE is not set but NATIVEPHP_PHP_BINARY_PATH is, the
 * app ships its own binaries, so "local" is used.
 */
trait ResolvesPhpBinary
{
    /**
     * The binary directory this builder has settled on, so the manifest and
     * downloads are only dealt with once per build or serve.
     */
    protected ?string $resolvedPhpBinaryPath = null;

    /**
     * Whether the R2 download resolver is enabled.
     */
    public function usesR2BinarySource(): bool
    {
        $source = strtolower((string) env('NATIVEPHP_BIN_SOURCE', ''));

        if ($source !== '') {
            return $source === 'r2';
        }

        return env('NATIVEPHP_PHP_BINARY_PATH') === null;
    }

    /**
     * Ensure the PHP binary for the given platform/version is present in the
     * per-user R2 cache and return the cache root directory (the value that
     * should be used for NATIVEPHP_PHP_BINARY_PATH).
     *
     * os/arch/version default to the current host when not supplied, matching
     * the host detection used by resources/electron/php.js for serve/install.
     */
    public function resolvePhpBinaryFromR2(
        ?string $os = null,
        ?string $arch = null,
        ?string $version = null
    ): string {
        $os ??= $this->currentOs();
        $arch ??= $this->currentArch();
        $version ??= $this->currentPhpVersion();

        return $this->phpBinaryDownloader()->ensure($os, $arch, $version);
    }

    /**
     * Pre-download every PHP binary needed for a build target into the cache,
     * so that resources/electron/php.js finds them already present regardless of
     * which {os}/{arch} it ultimately requests.
     *
     * Accepts a NativePHP build target selector such as "mac", "mac-arm64",
     * "linux-x64", "win" or "all".
     *
     * @return string The cache root directory.
     */
    public function prefetchPhpBinariesForBuild(string $buildTarget, ?string $version = null): string
    {
        $version ??= $this->currentPhpVersion();

        return $this->resolvedPhpBinaryPath = $this->withBundledFallback(function () use ($buildTarget, $version) {
            $cacheRoot = $this->phpBinaryDownloader()->cacheRoot();

            foreach ($this->expandBuildTargets($buildTarget) as [$os, $arch]) {
                $cacheRoot = $this->phpBinaryDownloader()->ensure($os, $arch, $version);
            }

            return $cacheRoot;
        });
    }

    /**
     * Run an R2 resolve. When R2 can't provide a binary (offline, manifest
     * missing, platform not published yet), fall back to nativephp/php-bin
     * if the app has installed it.
     *
     * php.js reads every binary from a single directory, so a build either
     * gets all of its binaries from R2 or all of them from php-bin.
     */
    protected function withBundledFallback(callable $resolve): string
    {
        try {
            return $resolve();
        } catch (RuntimeException $e) {
            if (! is_dir($this->bundledPhpBinaryPath())) {
                throw new RuntimeException(
                    'Could not get the PHP binary from R2. '.$e->getMessage()
                    .' To work offline, install the bundled binaries with `composer require nativephp/php-bin`.',
                    previous: $e
                );
            }

            warning('Could not get the PHP binary from R2, using the nativephp/php-bin binaries instead, which may be out of date. '.$e->getMessage());

            return $this->bundledPhpBinaryPath();
        }
    }

    /**
     * Expand a build target selector into concrete [os, arch] pairs.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function expandBuildTargets(string $buildTarget): array
    {
        $matrix = [
            'mac' => ['x64', 'arm64'],
            'linux' => ['x64', 'arm64'],
            'win' => ['x64'],
        ];

        // "mac-arm64" → os "mac", arch "arm64"
        if (str_contains($buildTarget, '-')) {
            [$os, $arch] = explode('-', $buildTarget, 2);

            if (isset($matrix[$os]) && in_array($arch, $matrix[$os], true)) {
                return [[$os, $arch]];
            }
        }

        // "all" → every os/arch combination.
        if ($buildTarget === 'all') {
            $targets = [];
            foreach ($matrix as $os => $archs) {
                foreach ($archs as $arch) {
                    $targets[] = [$os, $arch];
                }
            }

            return $targets;
        }

        // Bare os (e.g. "mac") → all of that os's architectures.
        if (isset($matrix[$buildTarget])) {
            return array_map(fn ($arch) => [$buildTarget, $arch], $matrix[$buildTarget]);
        }

        // Unknown selector: fall back to the current host.
        return [[$this->currentOs(), $this->currentArch()]];
    }

    protected function phpBinaryDownloader(): PhpBinaryDownloader
    {
        if (function_exists('app') && app()->bound(PhpBinaryDownloader::class)) {
            return app(PhpBinaryDownloader::class);
        }

        return new PhpBinaryDownloader;
    }

    /**
     * The PHP version string (e.g. "8.3") to resolve, matching what
     * BuildCommand / ExecuteCommand wire into NATIVEPHP_PHP_BINARY_VERSION.
     */
    protected function currentPhpVersion(): string
    {
        return PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    }

    /**
     * Detect the current host OS using the NativePHP os keys (mac|linux|win).
     */
    protected function currentOs(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'mac',
            'Linux' => 'linux',
            default => 'linux',
        };
    }

    /**
     * Detect the current host architecture using the NativePHP arch keys
     * (arm64|x64). Mirrors php.js's process.arch handling.
     */
    protected function currentArch(): string
    {
        $machine = strtolower((string) php_uname('m'));

        if (str_contains($machine, 'arm64') || str_contains($machine, 'aarch64')) {
            return 'arm64';
        }

        return 'x64';
    }
}
