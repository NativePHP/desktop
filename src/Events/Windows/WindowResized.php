<?php

namespace Native\Desktop\Events\Windows;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

class WindowResized implements DispatchableFromNative, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $id, public int $width, public int $height)
    {
        //
    }

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
