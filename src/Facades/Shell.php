<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;
use Native\Desktop\Contracts\Shell as ShellContract;
use Native\Desktop\Fakes\ShellFake;

/**
 * @method static void showInFolder(string $path)
 * @method static string openFile(string $path)
 * @method static void trashFile(string $path)
 * @method static void openExternal(string $url)
 */
class Shell extends Facade
{
    public static function fake()
    {
        return tap(static::getFacadeApplication()->make(ShellFake::class), function ($fake) {
            static::swap($fake);
        });
    }

    protected static function getFacadeAccessor()
    {
        self::clearResolvedInstance(ShellContract::class);

        return ShellContract::class;
    }
}
