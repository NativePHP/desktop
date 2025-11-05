<?php

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Traits\Conditionable;
use Native\Desktop;

describe('architecture', function () {

    arch('ensure api is conditionable')
        ->expect([
            Desktop\App::class,
            Desktop\Dock::class,
            Desktop\Alert::class,
            Desktop\Clipboard::class,
            Desktop\AutoUpdater::class,
            Desktop\ChildProcess::class,
            Desktop\Notification::class,
            Desktop\Menu\Menu::class,
            Desktop\Windows\Window::class,
            Desktop\Windows\WindowManager::class,

            Desktop\Fakes\ChildProcessFake::class,
            Desktop\Fakes\WindowManagerFake::class,
        ])->each->toUseTrait(Conditionable::class);

})->skip(function () {
    $version = Process::run('vendor/bin/pest --version')->throw()->output();

    // Cleanup output
    $version = preg_replace('/\e\[[0-9;]*m/', '', $version);
    $version = trim(preg_replace('/[^\d.]/', '', $version));

    // Only run test when pest version is at least 3
    return version_compare($version, '3.0.0', '<');
}, 'Test not supporten on Pest < v3');
