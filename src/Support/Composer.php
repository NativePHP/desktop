<?php

namespace Native\Desktop\Support;

use Composer\InstalledVersions;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class Composer
{
    public static function desktopPackagePath(string $path = '')
    {
        return Path::join(__DIR__, '../../', $path);
    }

    public static function phpPackagePath(string $path = '')
    {
        return self::vendorPath("nativephp/php-bin/{$path}");
    }

    public static function vendorPath(string $path = '')
    {
        $rootPath = realpath(InstalledVersions::getRootPackage()['install_path']);

        return Path::join($rootPath, 'vendor', $path);
    }

    public static function composerFileContents(): object
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')));
        throw_unless($composer, RuntimeException::class, "composer.json couldn't be parsed");

        return $composer;
    }

    public static function installDevScript()
    {
        $composer = self::composerFileContents();
        $composerScripts = $composer->scripts ?? (object) [];

        info('Installing `composer native:dev` script alias...');

        if ($composerScripts->{'native:dev'} ?? false) {
            note('native:dev script already installed... skipping.');

            return;
        }

        $composerScripts->{'native:dev'} = [
            'Composer\\Config::disableProcessTimeout',
            'npx concurrently -k -c "#93c5fd,#c4b5fd" "php artisan native:run --no-interaction" "npm run dev" --names=app,vite',
        ];

        data_set($composer, 'scripts', $composerScripts);

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        note('native:dev script installed!');
    }

    public static function installUpdateScript(bool $publish = false)
    {
        $composer = self::composerFileContents();
        $postUpdateScripts = data_get($composer, 'scripts.post-update-cmd', []);

        $scriptSuffix = $publish ? '--publish' : '';
        $installScript = "@php artisan native:install --force --quiet {$scriptSuffix}";

        info("Installing `native:install {$scriptSuffix}` post-update-cmd script");

        foreach ($postUpdateScripts as $key => $script) {
            if (str_contains($script, 'native:install')) {
                $hasPublishFlag = str_contains($script, '--publish');

                // The install script is present with or without the expected publish flag
                if ($hasPublishFlag === $publish) {
                    note("`native:install {$scriptSuffix}` script already present in post-update-cmd... skipping.");

                    return;
                }

                // The install script is present but the --publish flag
                // doesn't match what's expected. We can unset it.
                unset($postUpdateScripts[$key]);
            }
        }

        $postUpdateScripts[] = $installScript;

        data_set($composer, 'scripts.post-update-cmd', array_values($postUpdateScripts));

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        note('post-update-cmd script installed!');
    }
}
