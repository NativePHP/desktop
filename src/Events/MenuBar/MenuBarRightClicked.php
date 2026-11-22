<?php

namespace Native\Desktop\Events\MenuBar;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to SupaNative\Desktop\MenuBar\Events\MenuBarRightClicked, with the key of the item. Nothing changes in v2.
 */
class MenuBarRightClicked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $combo, public array $bounds) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
