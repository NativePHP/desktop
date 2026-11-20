<?php

namespace Native\Desktop\Events\Menu;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to Menu\Events\MenuItemClicked, with the same constructor and typed id, label, checked and data. It is no longer broadcast on the nativephp channel: Event::listen and a screen's #[On] hear it. Nothing changes in v2.
 */
class MenuItemClicked implements ShouldBroadcastNow
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
