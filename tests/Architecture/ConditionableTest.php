<?php

use Illuminate\Support\Traits\Conditionable;

arch()
    ->expect([
        Native\Desktop\Alert::class,
        Native\Desktop\App::class,
        Native\Desktop\AutoUpdater::class,
        Native\Desktop\ChildProcess::class,
        Native\Desktop\Clipboard::class,
        Native\Desktop\Dock::class,
        Native\Desktop\Menu\Menu::class,
        Native\Desktop\Notification::class,
        Native\Desktop\Windows\Window::class,
        Native\Desktop\Windows\WindowManager::class,

        Native\Desktop\Fakes\ChildProcessFake::class,
        Native\Desktop\Fakes\WindowManagerFake::class,
    ])->each->toUseTrait(Conditionable::class);
