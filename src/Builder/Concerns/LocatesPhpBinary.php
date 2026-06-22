<?php

namespace Native\Desktop\Builder\Concerns;

trait LocatesPhpBinary
{
    use ResolvesPhpBinary;

    abstract public function sourcePath(string $path = ''): string;

    /**
     * @return string The path to the binary package directory
     */
    protected function binaryPackageDirectory(): string
    {
        return env('NATIVEPHP_PHP_BINARY_PATH', 'vendor/nativephp/php-bin/');
    }

    /**
     * Returns the path to the directory containing the PHP binary zips,
     * laid out as <path>/<os>/<arch>/php-<version>.zip.
     *
     * When NATIVEPHP_BIN_SOURCE=r2 this resolves (and, if needed, downloads)
     * the binary into a per-user cache and returns that cache directory, so the
     * existing php.js / build flow works unchanged. Otherwise it returns the
     * bundled vendor/nativephp/php-bin/bin/ directory (the default behaviour).
     *
     * @return string The path to the PHP binary directory (not including the filename)
     */
    public function phpBinaryPath(): string
    {
        if ($this->usesR2BinarySource()) {
            return $this->resolvePhpBinaryFromR2();
        }

        return $this->sourcePath($this->binaryPackageDirectory().'bin/');
    }
}
