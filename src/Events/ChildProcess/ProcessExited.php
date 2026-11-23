<?php

namespace Native\Desktop\Events\ChildProcess;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to ChildProcess\Events\ProcessExited, which adds $pid. It is no longer broadcast on the nativephp channel: Event::listen and a screen's #[On] hear it. Nothing changes in v2.
 */
class ProcessExited implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $alias, public int $code) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
