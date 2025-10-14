<?php

/**
 * This trait is responsible for managing .env file operations including:
 * - Cleaning sensitive information during builds
 * - Updating/removing individual environment variables
 * - Reading environment values
 */

namespace Native\Desktop\Builder\Concerns;

trait ManagesEnvFile
{
    abstract public function buildPath(string $path = ''): string;

    public array $overrideKeys = [
        'LOG_CHANNEL',
        'LOG_STACK',
        'LOG_DAILY_DAYS',
        'LOG_LEVEL',
    ];

    /**
     * Clean sensitive information from .env file for production builds
     */
    public function cleanEnvFile(?string $envPath = null): void
    {
        $envFile = $envPath ?? $this->getEnvPath();
        $cleanUpKeys = array_merge($this->overrideKeys, config('nativephp.cleanup_env_keys', []));

        $contents = collect(file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            // Remove cleanup keys
            ->filter(function (string $line) use ($cleanUpKeys) {
                $key = str($line)->before('=');

                return ! $key->is($cleanUpKeys)
                    && ! $key->startsWith('#');
            })
            // Set defaults (other config overrides are handled in the NativeServiceProvider)
            // The Log channel needs to be configured before anything else.
            ->push('LOG_CHANNEL=stack')
            ->push('LOG_STACK=daily')
            ->push('LOG_DAILY_DAYS=3')
            ->push('LOG_LEVEL=warning')
            ->join("\n");

        file_put_contents($envFile, $contents);
    }

    /**
     * Update or add an environment variable
     */
    public function updateEnvFile(string $key, string $value, ?string $envPath = null): void
    {
        $envPath = $envPath ?? $this->getEnvPath();
        $envContent = file_get_contents($envPath);

        $pattern = "/^{$key}=.*$/m";

        if (preg_match($pattern, $envContent)) {
            $envContent = preg_replace($pattern, "{$key}={$value}", $envContent);
        } else {
            $envContent = rtrim($envContent, "\n")."\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $envContent);
    }

    /**
     * Remove environment variables
     */
    public function removeFromEnvFile(array $keys, ?string $envPath = null): void
    {
        $envPath = $envPath ?? $this->getEnvPath();
        $envContent = file_get_contents($envPath);

        foreach ($keys as $key) {
            $envContent = preg_replace("/^{$key}=.*$/m", '', $envContent);
        }

        // Clean up extra newlines
        $envContent = preg_replace('/\n\n+/', "\n\n", $envContent);
        $envContent = trim($envContent)."\n";

        file_put_contents($envPath, $envContent);
    }

    /**
     * Get an environment variable value
     */
    public function getEnvValue(string $key, ?string $envPath = null): ?string
    {
        $envPath = $envPath ?? $this->getEnvPath();

        if (! file_exists($envPath)) {
            return null;
        }

        $envContent = file_get_contents($envPath);

        if (preg_match("/^{$key}=(.*)$/m", $envContent, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Get the appropriate .env file path based on context
     */
    private function getEnvPath(): string
    {
        return $this->buildPath('app/'.app()->environmentFile());
    }
}
