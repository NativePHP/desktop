<?php

namespace Native\Desktop\Builder\Concerns;

use Native\Desktop\Builder\PhpBinaryDownloader;

/**
 * Local-first download resolver for NativePHP desktop PHP binaries.
 *
 * Gated behind NATIVEPHP_BIN_SOURCE:
 *   - "local" (default): bundled vendor/nativephp/php-bin behaviour (unchanged).
 *   - "r2": resolve the binary from Cloudflare R2 (via the versions.json
 *     manifest) into a per-user cache and point NATIVEPHP_PHP_BINARY_PATH there.
 */
trait ResolvesPhpBinary
{
    /**
     * Whether the R2 download resolver is enabled.
     */
    public function usesR2BinarySource(): bool
    {
        return strtolower((string) env('NATIVEPHP_BIN_SOURCE', 'local')) === 'r2';
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

        $cacheRoot = $this->phpBinaryDownloader()->cacheRoot();

        foreach ($this->expandBuildTargets($buildTarget) as [$os, $arch]) {
            $cacheRoot = $this->phpBinaryDownloader()->ensure($os, $arch, $version);
        }

        return $cacheRoot;
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
