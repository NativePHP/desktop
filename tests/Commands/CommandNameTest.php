<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Illuminate\Database\Console\WipeCommand as BaseWipeCommand;
use Native\Desktop\Commands\FreshCommand;
use Native\Desktop\Commands\WipeDatabaseCommand;
use Symfony\Component\Console\Command\Command;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
beforeEach(function () {
    $this->commands = app(Kernel::class)->all();
});

function optionNames(Command $command): array
{
    return array_keys($command->getDefinition()->getOptions());
}

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/
it('registers the native commands under their own names', function () {
    expect($this->commands)->toHaveKeys(['native:migrate:fresh', 'native:db:wipe'])
        ->and($this->commands['native:migrate:fresh'])->toBeInstanceOf(FreshCommand::class)
        ->and($this->commands['native:db:wipe'])->toBeInstanceOf(WipeDatabaseCommand::class);
});

it('leaves the commands they extend registered under the Laravel names', function () {
    expect($this->commands['migrate:fresh'])->toBeInstanceOf(BaseFreshCommand::class)
        ->not->toBeInstanceOf(FreshCommand::class)
        ->and($this->commands['db:wipe'])->toBeInstanceOf(BaseWipeCommand::class)
        ->not->toBeInstanceOf(WipeDatabaseCommand::class);
});

it('carries over every option the parent commands define', function () {
    expect(optionNames($this->commands['native:migrate:fresh']))
        ->toContain(...optionNames(new BaseFreshCommand(app('migrator'))))
        ->and(optionNames($this->commands['native:db:wipe']))
        ->toContain(...optionNames(new BaseWipeCommand));
});
