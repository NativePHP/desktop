<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void checkForUpdates()
 * @method static void quitAndInstall()
 * @method static void downloadUpdate()
 * @method static static when($value = null, ?callable $callback = null, ?callable $default = null)
 * @method static static unless($value = null, ?callable $callback = null, ?callable $default = null)
 */
class AutoUpdater extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\AutoUpdater::class;
    }
}
