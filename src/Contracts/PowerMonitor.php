<?php

namespace Native\Desktop\Contracts;

use Native\Desktop\Enums\SystemIdleStatesEnum;
use Native\Desktop\Enums\ThermalStatesEnum;

/**
 * @deprecated v3 removes this interface with the PowerMonitor facade. Its methods move to System there. Nothing changes in v2.
 */
interface PowerMonitor
{
    public function getSystemIdleState(int $threshold): SystemIdleStatesEnum;

    public function getSystemIdleTime(): int;

    public function getCurrentThermalState(): ThermalStatesEnum;

    public function isOnBatteryPower(): bool;
}
