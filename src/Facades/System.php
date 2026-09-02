<?php

namespace Native\Desktop\Facades;

use Illuminate\Support\Facades\Facade;
use Native\Desktop\Enums\SystemThemesEnum;

/**
 * @method static bool canPromptTouchID()
 * @method static bool promptTouchID(string $reason)
 * @method static bool canEncrypt()
 * @method static string encrypt(string $string)
 * @method static string decrypt(string $string)
 * @method static array printers()
 * @method static void print(string $html, ?\Native\Desktop\DataObjects\Printer $printer = null, ?array $settings = [])
 * @method static bool printFile(string $path, \Native\Desktop\DataObjects\Printer|string|null $printer = null, ?array $settings = [])
 * @method static string printToPDF(string $html, ?array $settings = [])
 * @method static string timezone()
 * @method static SystemThemesEnum theme(?SystemThemesEnum $theme = null)
 */
class System extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Native\Desktop\System::class;
    }
}
