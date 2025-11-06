<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void clear()
 * @method static string text($text = null)
 * @method static string html($html = null)
 * @method static string|null image($image = null)
 * @method static static when($value = null, ?callable $callback = null, ?callable $default = null)
 * @method static static unless($value = null, ?callable $callback = null, ?callable $default = null)
 */
class Clipboard extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\Clipboard::class;
    }
}
