<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Database\Console\Seeds\SeedCommand as BaseSeedCommand;
use Illuminate\Database\Console\WipeCommand as BaseWipeCommand;
use Native\Desktop\Commands\FreshCommand;
use Native\Desktop\Commands\MigrateCommand;
use Native\Desktop\Commands\SeedDatabaseCommand;
use Native\Desktop\Commands\WipeDatabaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
$nativeCommands = [
    'native:migrate' => [MigrateCommand::class, BaseMigrateCommand::class],
    'native:migrate:fresh' => [FreshCommand::class, BaseFreshCommand::class],
    'native:seed' => [SeedDatabaseCommand::class, BaseSeedCommand::class],
    'native:db:wipe' => [WipeDatabaseCommand::class, BaseWipeCommand::class],
];

beforeEach(function () {
    $this->commands = app(Kernel::class)->all();
});

function attributeName(string $class): ?string
{
    $attribute = (new ReflectionClass($class))->getAttributes(AsCommand::class);

    return empty($attribute) ? null : $attribute[0]->newInstance()->name;
}

function optionNames(Command $command): array
{
    return array_keys($command->getDefinition()->getOptions());
}

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/
it('registers the native commands under their own names', function (string $name, string $class) {
    expect($this->commands)->toHaveKey($name)
        ->and($this->commands[$name])->toBeInstanceOf($class);
})->with(array_map(fn ($name, $classes) => [$name, $classes[0]], array_keys($nativeCommands), $nativeCommands));

it('leaves the commands they extend registered under the Laravel names', function () {
    expect($this->commands['migrate:fresh'])->toBeInstanceOf(BaseFreshCommand::class)
        ->not->toBeInstanceOf(FreshCommand::class)
        ->and($this->commands['db:wipe'])->toBeInstanceOf(BaseWipeCommand::class)
        ->not->toBeInstanceOf(WipeDatabaseCommand::class);
});

// Commands are registered lazily under the name in their attribute, but named
// from their signature once built. Any disagreement between the two
// makes Artisan fail with "registered under multiple names".
it('names each native command the same way in its attribute and its signature', function (string $name, string $class) {
    expect(attributeName($class))->toBe($name)
        ->and($this->commands[$name]->getName())->toBe($name);
})->with(array_map(fn ($name, $classes) => [$name, $classes[0]], array_keys($nativeCommands), $nativeCommands));

it('describes the native commands rather than inheriting the Laravel wording', function (string $name, string $parent) {
    expect($this->commands[$name]->getDescription())
        ->not->toBe((new ReflectionClass($parent))->getDefaultProperties()['description'])
        ->toContain('NativePHP development environment');
})->with(array_map(fn ($name, $classes) => [$name, $classes[1]], array_keys($nativeCommands), $nativeCommands));

it('carries over every option the parent commands define', function () {
    expect(optionNames($this->commands['native:migrate:fresh']))
        ->toContain(...optionNames(new BaseFreshCommand(app('migrator'))))
        ->and(optionNames($this->commands['native:db:wipe']))
        ->toContain(...optionNames(new BaseWipeCommand));
});
