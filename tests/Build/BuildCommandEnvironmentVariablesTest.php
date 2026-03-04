<?php

use Illuminate\Support\Facades\Config;
use Native\Desktop\Drivers\Electron\Commands\BuildCommand;

it('passes NSIS delete app data flag as true when config is enabled', function () {
    Config::set('nativephp.nsis.delete_app_data_on_uninstall', true);

    $command = app(BuildCommand::class);
    $envVars = (new ReflectionMethod($command, 'getEnvironmentVariables'))->invoke($command);

    expect($envVars['NATIVEPHP_NSIS_DELETE_APP_DATA'])->toBe('true');
});

it('passes NSIS delete app data flag as false when config is disabled', function () {
    Config::set('nativephp.nsis.delete_app_data_on_uninstall', false);

    $command = app(BuildCommand::class);
    $envVars = (new ReflectionMethod($command, 'getEnvironmentVariables'))->invoke($command);

    expect($envVars['NATIVEPHP_NSIS_DELETE_APP_DATA'])->toBe('false');
});

it('defaults NSIS delete app data flag to false when config is not set', function () {
    Config::set('nativephp.nsis.delete_app_data_on_uninstall', null);

    $command = app(BuildCommand::class);
    $envVars = (new ReflectionMethod($command, 'getEnvironmentVariables'))->invoke($command);

    expect($envVars['NATIVEPHP_NSIS_DELETE_APP_DATA'])->toBe('false');
});
