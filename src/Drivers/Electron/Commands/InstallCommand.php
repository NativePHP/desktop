<?php

namespace Native\Desktop\Drivers\Electron\Commands;

use Illuminate\Console\Command;
use Native\Desktop\Drivers\Electron\Traits\CreatesElectronProject;
use Native\Desktop\Drivers\Electron\Traits\Installer;
use Native\Desktop\Support\Composer;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

#[AsCommand(
    name: 'native:install',
    description: 'Install all of the NativePHP resources',
)]
class InstallCommand extends Command
{
    use CreatesElectronProject;
    use Installer;

    protected $signature = 'native:install
        {--force : Overwrite existing files by default}
        {--publish : Publish the Electron project to your project\'s root}
        {--installer=npm : The package installer to use: npm, yarn or pnpm}';

    public function handle(): void
    {
        $force = $this->option('force');
        $publish = $this->option('publish');
        $withoutInteraction = $this->option('no-interaction');

        // Prompt for publish
        $shouldPromptForPublish = ! $force && ! $withoutInteraction;
        if (! $publish && $shouldPromptForPublish) {
            $publish = confirm(
                label: 'Would you like to publish the Electron project?',
                hint: 'You\'ll only need this if you\'d like to customize NativePHP\'s inner workings.',
                default: false
            );
        }

        // Prompt to install NPM Dependencies
        $installer = $this->getInstaller($this->option('installer'));
        $this->installNPMDependencies(
            force: $force,
            installer: $installer,
            withoutInteraction: $withoutInteraction
        );

        // Publish Electron project
        if ($publish) {
            intro('Creating Electron project');
            $installPath = base_path('nativephp/electron');
            $this->createElectronProject($installPath);
            info('Created Electron project in `./nativephp/electron`');
        }

        // Install Composer scripts
        intro('Installing composer scripts');
        Composer::installDevScript();

        // Install `native:install` script with a --publish flag
        // if either publishing now or already published
        $publish || file_exists(base_path('nativephp/electron/package.json'))
            ? Composer::installUpdateScript(publish: true)
            : Composer::installUpdateScript();

        // Publish provider & config
        intro('Publishing NativePHP Service Provider...');
        $this->call('vendor:publish', ['--tag' => 'nativephp-provider']);
        $this->call('vendor:publish', ['--tag' => 'nativephp-config']);

        // Promt to serve the app
        $shouldPromptForServe = ! $withoutInteraction && ! $force;
        if ($shouldPromptForServe && confirm('Would you like to start the NativePHP development server', false)) {
            $this->call('native:run', [
                '--installer' => $installer,
                '--no-dependencies',
                '--no-interaction' => $withoutInteraction,
            ]);
        }

        outro('NativePHP scaffolding installed successfully.');
    }
}
