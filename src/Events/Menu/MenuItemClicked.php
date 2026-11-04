<?php

namespace Native\Desktop\Events\Menu;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

class MenuItemClicked implements DispatchableFromNative, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $item, public array $combo = []) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
