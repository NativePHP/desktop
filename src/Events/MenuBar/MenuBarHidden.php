<?php

namespace Native\Desktop\Events\MenuBar;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 removes this event: the panel of a menu bar item is a window, so WindowShown and WindowHidden fire for it, with the item's key. Nothing changes in v2.
 */
class MenuBarHidden implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
