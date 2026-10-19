<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @deprecated v3 renames this facade to Display. displays() becomes Display::all() and getCenterOfActiveScreen() becomes Display::center(); primary(), active() and cursorPosition() keep their names. Nothing changes in v2.
 *
 * @method static object cursorPosition()
 * @method static array displays()
 * @method static array getCenterOfActiveScreen()
 * @method static array active()
 * @method static array primary()
 */
class Screen extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\Screen::class;
    }
}
