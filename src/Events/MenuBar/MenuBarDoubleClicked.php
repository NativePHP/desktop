<?php

namespace Native\Desktop\Events\MenuBar;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 removes this event, as a double click also fires two single clicks. Nothing changes in v2.
 */
class MenuBarDoubleClicked implements ShouldBroadcastNow
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
