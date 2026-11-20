<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;
use Native\Desktop\Menu\Menu;

/**
 * @deprecated v3 removes this facade. A menu that a right click opens is :context-menu on a <native:pressable> element. Nothing changes in v2.
 *
 * @method static void register(Menu $menu)
 * @method static void remove()
 */
class ContextMenu extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\ContextMenu::class;
    }
}
