<?php

namespace Native\Desktop\Builder;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Filesystem\Path;
use ZipArchive;

use function Laravel\Prompts\task;

/**
 * Resolves NativePHP desktop PHP binaries from Cloudflare R2 (via the
 * https://bin.nativephp.com/{branch}/desktop/versions.json manifest) into a
 * per-user cache, local-first.
 *
 * Mirrors the blessed local-first → download → validate flow used by
 * nativephp-mobile-air's InstallsAndroid / InstallsIos traits.
 */
class PhpBinaryDownloader
{
    /**
     * Maximum number of download attempts before giving up.
     */
    protected int $maxAttempts = 3;

    /**
     * Ensure the PHP binary zip for the given {os}-{arch}/version exists in the
     * per-user cache, downloading it from R2 if necessary, and return the cache
     * root directory.
     *
     * The returned directory is laid out as:
     *   <cacheRoot>/<os>/<arch>/php-<version>.zip
     * so it can be handed straight to NATIVEPHP_PHP_BINARY_PATH and consumed,
     * unchanged, by resources/electron/php.js and LocatesPhpBinary.
     *
     * @return string Absolute path to the cache root directory (no trailing slash forced).
     */
    public function ensure(string $os, string $arch, string $version): string
    {
        $cacheRoot = $this->cacheRoot();
        $targetFile = $this->cachedZipPath($os, $arch, $version);

        $expectedSha256 = null;
        $entry = null;

        // Local-first: if the zip is already cached and (when we can cheaply
        // know it) we have no reason to distrust it, short-circuit the download.
        if (is_file($targetFile)) {
            // We don't have manifest metadata yet, so trust an existing valid
            // cached file unless we later learn its sha256 doesn't match.
            if ($this->isValidZip($targetFile)) {
                return $cacheRoot;
            }

            // Corrupt cache entry — remove it and fall through to re-download.
            @unlink($targetFile);
        }

        // Fetch the manifest and resolve the entry for this platform/version.
        $manifest = $this->fetchManifest();
        $entry = $this->selectEntry($manifest, $os, $arch, $version);

        [$url, $expectedSha256, $expectedSize] = $this->normaliseEntry($entry);

        $this->ensureDirectoryExists(dirname($targetFile));

        $platformKey = "{$os}-{$arch}";

        $lastError = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            $label = $attempt === 1
                ? "Downloading PHP {$version} binary ({$platformKey})"
                : "Downloading PHP {$version} binary ({$platformKey}) — retry {$attempt}/{$this->maxAttempts}";

            $tempFile = $targetFile.'.'.bin2hex(random_bytes(6)).'.part';

            $ok = task($label, function () use ($url, $tempFile, $expectedSha256, &$lastError) {
                try {
                    $this->downloadTo($url, $tempFile);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    @unlink($tempFile);

                    return false;
                }

                // Validate: prefer sha256 when the manifest provides it,
                // otherwise fall back to verifying it is a real zip archive.
                if ($expectedSha256 !== null) {
                    $actual = hash_file('sha256', $tempFile);

                    if (! hash_equals(strtolower($expectedSha256), strtolower((string) $actual))) {
                        $lastError = 'SHA-256 mismatch (expected '.$expectedSha256.', got '.$actual.')';
                        @unlink($tempFile);

                        return false;
                    }
                } elseif (! $this->isValidZip($tempFile)) {
                    $lastError = 'Downloaded file is not a valid ZIP archive';
                    @unlink($tempFile);

                    return false;
                }

                return true;
            });

            if ($ok) {
                // Atomic publish: temp file → final cache path.
                if (! @rename($tempFile, $targetFile)) {
                    @unlink($tempFile);

                    throw new RuntimeException("Failed to move downloaded PHP binary into cache at {$targetFile}.");
                }

                return $cacheRoot;
            }
        }

        $message = "Failed to download PHP {$version} binary for {$platformKey} from R2 after {$this->maxAttempts} attempts.";

        if ($lastError !== null) {
            $message .= " Last error: {$lastError}.";
        }

        $message .= ' Check your internet connection, or set NATIVEPHP_BIN_SOURCE=local to use the bundled vendor/nativephp/php-bin binaries.';

        throw new RuntimeException($message);
    }

    /**
     * The per-user cache root: ~/.nativephp/bin (with ~ resolved properly).
     *
     * May be overridden with NATIVEPHP_BIN_CACHE_PATH (an absolute path or a
     * path beginning with ~ which is expanded to the user's home directory).
     */
    public function cacheRoot(): string
    {
        $override = env('NATIVEPHP_BIN_CACHE_PATH');

        if (is_string($override) && $override !== '') {
            return $this->expandTilde($override);
        }

        return Path::join($this->homeDirectory(), '.nativephp', 'bin');
    }

    /**
     * Expand a leading ~ to the user's home directory.
     */
    protected function expandTilde(string $path): string
    {
        if ($path === '~') {
            return $this->homeDirectory();
        }

        if (str_starts_with($path, '~/') || str_starts_with($path, '~\\')) {
            return Path::join($this->homeDirectory(), substr($path, 2));
        }

        return $path;
    }

    /**
     * Absolute path to the cached zip for a given platform/version.
     */
    public function cachedZipPath(string $os, string $arch, string $version): string
    {
        return Path::join($this->cacheRoot(), $os, $arch, "php-{$version}.zip");
    }

    /**
     * Fetch and decode the versions manifest from the configured branch.
     *
     * @return array<string, mixed>
     */
    protected function fetchManifest(): array
    {
        $url = $this->manifestUrl();

        try {
            $response = Http::timeout(60)->retry(2, 200)->get($url);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to fetch PHP binary manifest from {$url}: {$e->getMessage()}");
        }

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch PHP binary manifest from {$url} (HTTP {$response->status()}).");
        }

        $manifest = $response->json();

        if (! is_array($manifest) || ! isset($manifest['versions']) || ! is_array($manifest['versions'])) {
            throw new RuntimeException("PHP binary manifest at {$url} is malformed (missing 'versions').");
        }

        return $manifest;
    }

    /**
     * Pick the entry for versions[$version]["{os}-{arch}"] from the manifest.
     *
     * @param  array<string, mixed>  $manifest
     * @return string|array<string, mixed>
     */
    protected function selectEntry(array $manifest, string $os, string $arch, string $version)
    {
        $platformKey = "{$os}-{$arch}";

        if (! isset($manifest['versions'][$version])) {
            throw new RuntimeException(
                "PHP {$version} is not available in the {$this->branch()} manifest."
            );
        }

        $platforms = $manifest['versions'][$version];

        if (! is_array($platforms) || ! array_key_exists($platformKey, $platforms)) {
            throw new RuntimeException(
                "No PHP {$version} binary found for platform {$platformKey} in the {$this->branch()} manifest."
            );
        }

        $entries = $platforms[$platformKey];

        // An entry list may be a single entry or a list of entries.
        if ($this->isEntry($entries)) {
            return $entries;
        }

        if (is_array($entries) && ! empty($entries)) {
            $first = reset($entries);

            if ($this->isEntry($first)) {
                return $first;
            }
        }

        throw new RuntimeException(
            "Manifest entry for PHP {$version} {$platformKey} is empty or malformed."
        );
    }

    /**
     * Normalise a manifest entry (URL string OR {url, sha256, size}) into
     * [url, sha256|null, size|null].
     *
     * @param  string|array<string, mixed>  $entry
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    protected function normaliseEntry($entry): array
    {
        if (is_string($entry)) {
            return [$entry, null, null];
        }

        $url = $entry['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Manifest entry is missing a valid "url".');
        }

        $sha256 = isset($entry['sha256']) && is_string($entry['sha256']) && $entry['sha256'] !== ''
            ? $entry['sha256']
            : null;

        $size = isset($entry['size']) && is_numeric($entry['size'])
            ? (int) $entry['size']
            : null;

        return [$url, $sha256, $size];
    }

    /**
     * Whether the given value looks like a single manifest entry
     * (a URL string or an object with a "url" key).
     */
    protected function isEntry($value): bool
    {
        if (is_string($value)) {
            return true;
        }

        return is_array($value) && isset($value['url']);
    }

    /**
     * Stream a download to a file using the Laravel HTTP client.
     */
    protected function downloadTo(string $url, string $destination): void
    {
        $response = Http::timeout(600)
            ->withOptions(['sink' => $destination])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("HTTP {$response->status()} while downloading {$url}");
        }
    }

    /**
     * Validate that a file on disk is a readable ZIP archive (mirrors mobile-air).
     */
    protected function isValidZip(string $file): bool
    {
        if (! is_file($file) || filesize($file) === 0) {
            return false;
        }

        $zip = new ZipArchive;

        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $zip->close();

        return true;
    }

    protected function ensureDirectoryExists(string $dir): void
    {
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create cache directory: {$dir}");
        }
    }

    protected function manifestUrl(): string
    {
        return "https://bin.nativephp.com/{$this->branch()}/desktop/versions.json";
    }

    protected function branch(): string
    {
        return env('NATIVEPHP_BIN_BRANCH', 'main');
    }

    /**
     * Resolve the current user's home directory cross-platform.
     */
    protected function homeDirectory(): string
    {
        $home = getenv('HOME');

        if ($home === false || $home === '') {
            // Windows fallback.
            $drive = getenv('HOMEDRIVE');
            $path = getenv('HOMEPATH');

            if ($drive !== false && $path !== false) {
                $home = $drive.$path;
            }
        }

        if ($home === false || $home === '') {
            $home = getenv('USERPROFILE') ?: sys_get_temp_dir();
        }

        return $home;
    }
}
