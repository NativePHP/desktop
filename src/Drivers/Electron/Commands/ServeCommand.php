<?php

namespace Native\Desktop\Drivers\Electron\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\warning;

#[AsCommand(
    name: 'native:serve',
    description: '[deprecated] Start the NativePHP development server',
)]
class ServeCommand extends RunCommand
{
    protected $signature = 'native:serve {--no-queue} {--D|no-dependencies} {--installer=npm}';

    public function handle(): void
    {
        warning('[Deprecated] - The `native:serve` command has been replaced by `native:run` and will be removed in v3.x.');

        parent::handle();
    }
}
