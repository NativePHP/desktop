<?php

namespace Native\Desktop\Events\Windows;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to Window\Events\WindowShown, with the same $id, and a $surface. It is no longer broadcast on the nativephp channel: Event::listen and a screen's #[On] hear it. Nothing changes in v2.
 */
class WindowShown implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $id)
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
