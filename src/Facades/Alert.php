<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @deprecated v3 has no Alert facade. Alerts are Dialog::alert($title, $message, $buttons), with mobile's signature, and they no longer wait: show() returns nothing, and a button's callback, buttonPressed() or a ButtonPressed listener hears the press. show()'s message becomes $title, detail() becomes $message, buttons() becomes one action() per button, defaultId() becomes primary: true, cancelId() becomes style: 'cancel' and error() becomes type(AlertType::Error). title() has no v3 counterpart. Nothing changes in v2.
 *
 * @method static static new()
 * @method static static type(string $type)
 * @method static static title(string $title)
 * @method static static detail(string $detail)
 * @method static static buttons(string[] $buttons)
 * @method static static defaultId(int $defaultId)
 * @method static static cancelId(int $cancelId)
 * @method static int show(string $message)
 * @method static bool error(string $title, string $message)
 * @method static static when($value = null, ?callable $callback = null, ?callable $default = null)
 * @method static static unless($value = null, ?callable $callback = null, ?callable $default = null)
 */
class Alert extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\Alert::class;
    }
}
