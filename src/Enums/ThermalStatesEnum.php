<?php

namespace Native\Desktop\Enums;

/**
 * @deprecated v3 replaces this enum with core's ThermalState, which mobile shares. Its cases are Normal, Warm, Hot and Critical, for NOMINAL, FAIR, SERIOUS and CRITICAL, and UNKNOWN has no counterpart. Nothing changes in v2.
 */
enum ThermalStatesEnum: string
{
    case UNKNOWN = 'unknown';
    case NOMINAL = 'nominal';
    case FAIR = 'fair';
    case SERIOUS = 'serious';
    case CRITICAL = 'critical';
}
