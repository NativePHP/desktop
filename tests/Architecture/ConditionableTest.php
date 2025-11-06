<?php

use Illuminate\Support\Traits\Conditionable;
use Native\Desktop;

use function Pest\version;

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
    // Only run test when pest version is at least 3
    return version_compare(version(), '3.0.0', '<');
}, 'Test not supporten on Pest < v3');
