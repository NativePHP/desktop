<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @deprecated v3 has no Process facade. arch() moves to System::arch(), platform() to System::platform(), which answers macos, windows or linux, and uptime() to AppShell::uptime(). fresh() is removed, and its pid is AppShell::pid(). Nothing changes in v2.
 *
 * @method static string arch()
 * @method static string platform()
 * @method static float uptime()
 * @method static object fresh()
 */
class Process extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\Process::class;
    }
}
