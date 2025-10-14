<?php

namespace Native\Desktop\Drivers\Electron;

use Illuminate\Foundation\Application;
use Native\Desktop\Builder\Builder;
use Native\Desktop\Drivers\Electron\Commands\BuildCommand;
use Native\Desktop\Drivers\Electron\Commands\InstallCommand;
use Native\Desktop\Drivers\Electron\Commands\PublishCommand;
use Native\Desktop\Drivers\Electron\Commands\ResetCommand;
use Native\Desktop\Drivers\Electron\Commands\RunCommand;
use Native\Desktop\Drivers\Electron\Commands\ServeCommand;
use Native\Desktop\Drivers\Electron\Updater\UpdaterManager;
use Native\Desktop\Events\LivewireDispatcher;
use Native\Desktop\Support\Composer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ElectronServiceProvider extends PackageServiceProvider
{
    public static function electronPath(string $path = '')
    {
        // Will use the published electron project, or fallback to the vendor default
        $publishedProjectPath = base_path("nativephp/electron/{$path}");

        return file_exists("{$publishedProjectPath}/package.json")
            ? $publishedProjectPath
            : Composer::desktopPackagePath("resources/electron/{$path}");
    }

    public static function buildPath(string $path = '')
    {
        return Composer::desktopPackagePath("resources/build/{$path}");
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('nativephp-electron')
            ->hasCommands([
                InstallCommand::class,
                RunCommand::class,
                BuildCommand::class,
                PublishCommand::class,
                ResetCommand::class,
                ServeCommand::class, // Deprecated
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->bind('nativephp.updater', function (Application $app) {
            return new UpdaterManager($app);
        });

        $this->app->bind(Builder::class, function () {
            return Builder::make(
                buildPath: self::buildPath()
            );
        });
    }

    public function packageBooted(): void
    {
        app(LivewireDispatcher::class)->register();
    }
}
