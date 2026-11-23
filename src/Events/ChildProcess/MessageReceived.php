<?php

namespace Native\Desktop\Events\ChildProcess;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to ChildProcess\Events\MessageReceived, which fires once per line there. It is no longer broadcast on the nativephp channel: Event::listen and a screen's #[On] hear it. Nothing changes in v2.
 */
class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $alias, public mixed $data) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
