<?php

namespace Native\Desktop\Events\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

class NotificationClicked implements DispatchableFromNative, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $reference, public string $event) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
