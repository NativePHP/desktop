<?php

namespace Native\Desktop\Enums;

/**
 * @deprecated v3 renames this enum to System\Enums\SystemIdleState, with the same values and its cases in PascalCase: Active, Idle, Locked and Unknown. Nothing changes in v2.
 */
enum SystemIdleStatesEnum: string
{
    case ACTIVE = 'active';
    case IDLE = 'idle';
    case LOCKED = 'locked';
    case UNKNOWN = 'unknown';
}
