<?php

namespace Native\Desktop\Events\PowerMonitor;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Enums\PowerStatesEnum;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

class PowerStateChanged implements DispatchableFromNative, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PowerStatesEnum $state;

    public function __construct(string $state)
    {
        $this->state = PowerStatesEnum::from($state);
    }

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
