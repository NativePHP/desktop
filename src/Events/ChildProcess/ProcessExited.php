<?php

namespace Native\Desktop\Events\ChildProcess;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Native\Desktop\Events\Contracts\DispatchableFromNative;

class ProcessExited implements DispatchableFromNative, ShouldBroadcastNow
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
