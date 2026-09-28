<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void quit()
 * @method static void relaunch()
 * @method static void focus()
 * @method static void hide()
 * @method static bool isHidden()
 * @method static string getLocale() Deprecated: v3 moves this to System::language(), which v2 does not have yet.
 * @method static string getLocaleCountryCode() Deprecated: v3 moves this to System::region(), which v2 does not have yet.
 * @method static string getSystemLocale() Deprecated: v3 moves this to System::locale(), which v2 does not have yet.
 * @method static string version()
 * @method static int badgeCount($count = null) Deprecated: use Dock::badge(?string $label) instead, v3 removes this method.
 * @method static void addRecentDocument(string $path)
 * @method static array recentDocuments()
 * @method static void clearRecentDocuments()
 * @method static bool isRunningBundled()
 * @method static bool openAtLogin(?bool $open = null)
 * @method static bool isEmojiPanelSupported()
 * @method static void showEmojiPanel() Deprecated: v3 removes this method and replaces it with a Blade element. There is no v2 replacement.
 * @method static static when($value = null, ?callable $callback = null, ?callable $default = null)
 * @method static static unless($value = null, ?callable $callback = null, ?callable $default = null)
 */
class App extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\App::class;
    }
}
