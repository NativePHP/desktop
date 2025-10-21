<?php

/**
 * This trait is responsible for copying over the app to the build directory.
 * It skips any ignored paths/globs during the copy step
 */

namespace Native\Desktop\Builder\Concerns;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

use function Laravel\Prompts\warning;

trait CopiesToBuildDirectory
{
    abstract public function buildPath(string $path = ''): string;

    abstract public function sourcePath(string $path = ''): string;

    public function copyToBuildDirectory(): bool
    {
        $sourcePath = $this->sourcePath();
        $buildPath = $this->buildPath('app');

        $filesystem = new Filesystem;

        $patterns = array_unique(array_merge(
            config('nativephp-internal.cleanup_exclude_files', []),
            config('nativephp.cleanup_exclude_files', []),
        ));

        // Clean and create build directory
        $filesystem->remove($buildPath);
        $filesystem->mkdir($buildPath);

        // A filtered iterator that will exclude files matching our skip patterns
        $directory = new RecursiveDirectoryIterator(
            $sourcePath,
            RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS
        );

        $filter = new RecursiveCallbackFilterIterator($directory, function ($current) use ($patterns) {
            $relativePath = substr($current->getPathname(), strlen($this->sourcePath()) + 1);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath); // Windows

            // Check each skip pattern against the current file/directory
            foreach ($patterns as $pattern) {

                // fnmatch supports glob patterns like "*.txt" or "cache/*"
                if (fnmatch($pattern, $relativePath)) {
                    return false;
                }
            }

            return true;
        });

        // Now we walk all directories & files and copy them over accordingly
        $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $target = $buildPath.DIRECTORY_SEPARATOR.substr($item->getPathname(), strlen($sourcePath) + 1);

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }

                continue;
            }

            try {
                copy($item->getPathname(), $target);

                if (PHP_OS_FAMILY !== 'Windows') {
                    $perms = fileperms($item->getPathname());
                    if ($perms !== false) {
                        chmod($target, $perms);
                    }
                }
            } catch (Throwable $e) {
                warning('[WARNING] '.$e->getMessage().', file: '.$item->getPathname());
            }
        }

        $this->keepRequiredDirectories();
        $this->copyIncludedFiles();

        return true;
    }

    private function keepRequiredDirectories()
    {
        // Electron build removes empty folders, so we have to create dummy files
        // dotfiles unfortunately don't work.
        $filesystem = new Filesystem;
        $buildPath = $this->buildPath('app');

        $filesystem->dumpFile("{$buildPath}/storage/framework/cache/_native.json", '{}');
        $filesystem->dumpFile("{$buildPath}/storage/framework/sessions/_native.json", '{}');
        $filesystem->dumpFile("{$buildPath}/storage/framework/testing/_native.json", '{}');
        $filesystem->dumpFile("{$buildPath}/storage/framework/views/_native.json", '{}');
        $filesystem->dumpFile("{$buildPath}/storage/app/public/_native.json", '{}');
        $filesystem->dumpFile("{$buildPath}/storage/logs/_native.json", '{}');
    }

    private function copyIncludedFiles(): void
    {

        $sourcePath = $this->sourcePath();
        $buildPath = $this->buildPath('app');
        $filesystem = new Filesystem;

        $patterns = array_unique(array_merge(
            config('nativephp-internal.cleanup_include_files', []),
            config('nativephp.cleanup_include_files', []),
        ));

        foreach ($patterns as $pattern) {
            // Skip empty patterns
            if (empty($pattern)) {
                continue;
            }

            // Ensure pattern is relative (not absolute) for security
            // Prevents /absolute/path on Unix and C:\path on Windows
            if (str_starts_with($pattern, '/') || str_contains($pattern, '..') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:/', $pattern))) {
                warning("[WARNING] Skipping potentially unsafe include pattern: {$pattern}");

                continue;
            }

            // Normalize the pattern path separators
            $pattern = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $pattern);

            $matchingFiles = glob($sourcePath.DIRECTORY_SEPARATOR.$pattern, GLOB_BRACE);

            foreach ($matchingFiles as $sourceFile) {
                $relativePath = substr($sourceFile, strlen($sourcePath) + 1);
                $targetFile = $buildPath.'/'.$relativePath;

                // Create target directory if it doesn't exist
                $targetDir = dirname($targetFile);
                if (! is_dir($targetDir)) {
                    $filesystem->mkdir($targetDir, 0755);
                }

                // Copy the file
                if (is_file($sourceFile)) {
                    copy($sourceFile, $targetFile);

                    // Preserve permissions on non-Windows systems
                    if (PHP_OS_FAMILY !== 'Windows') {
                        $perms = fileperms($sourceFile);
                        if ($perms !== false) {
                            chmod($targetFile, $perms);
                        }
                    }
                }
            }
        }
    }
}
