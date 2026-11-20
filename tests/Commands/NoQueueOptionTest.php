<?php

use Native\Desktop\Builder\Builder;
use Native\Desktop\Drivers\Electron\Commands\RunCommand;
use Native\Desktop\Drivers\Electron\Commands\ServeCommand;

/*
|--------------------------------------------------------------------------
| Setup
|--------------------------------------------------------------------------
*/
// Skips the steps that install dependencies, patch files and start
// Electron, so a test runs the command without starting the app
// and only sees what the command itself puts on the console.
trait SkipsStartingTheApp
{
    protected function installNPMDependencies(bool $force, ?string $installer = 'npm', bool $withoutInteraction = false): void {}

    protected function patchPlist(): void {}

    protected function setAppNameAndVersion($developmentMode = false): string
    {
        return '';
    }

    public function installIcon() {}

    protected function runDeveloper(string $installer, bool $skip_queue, bool $no_focus = false, bool $withoutInteraction = false): void {}
}

beforeEach(function () {
    $builder = Mockery::spy(Builder::class);

    $this->app->instance(RunCommand::class, new class($builder) extends RunCommand
    {
        use SkipsStartingTheApp;
    });

    $this->app->instance(ServeCommand::class, new class($builder) extends ServeCommand
    {
        use SkipsStartingTheApp;
    });
});

/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/
it('warns that the no-queue option is deprecated', function (string $command) {
    $this->artisan($command, ['--no-queue' => true])
        ->expectsOutputToContain('The `--no-queue` option does nothing and will be removed in v3.x.')
        ->assertSuccessful();
})->with(['native:run', 'native:serve']);

it('does not warn about the no-queue option when it is not passed', function (string $command) {
    $this->artisan($command)
        ->doesntExpectOutputToContain('--no-queue')
        ->assertSuccessful();
})->with(['native:run', 'native:serve']);
