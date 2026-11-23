<?php

namespace Native\Desktop\Enums;

/**
 * @deprecated v3 renames this enum to System\Enums\PowerState, with the same values and its cases in PascalCase: Ac and Battery. Nothing changes in v2.
 */
enum PowerStatesEnum: string
{
    case AC = 'on-ac';
    case BATTERY = 'on-battery';
}
