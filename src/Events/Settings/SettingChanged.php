<?php

namespace Native\Desktop\Events\Settings;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to Settings\Events\SettingChanged, with the same constructor. It is no longer broadcast on the nativephp channel, and Event::listen hears it. Nothing changes in v2.
 */
class SettingChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $key, public mixed $value) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
