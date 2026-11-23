<?php

namespace Native\Desktop\Events\Notifications;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to Notification\Events\NotificationReply. There $reference becomes $id, $event goes, and it carries the reply button's $label and the notification's $data beside $reply. It is no longer broadcast on the nativephp channel: Event::listen and a screen's #[On] hear it. Nothing changes in v2.
 */
class NotificationReply implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $reference, public string $reply, public string $event) {}

    public function broadcastOn()
    {
        return [
            new Channel('nativephp'),
        ];
    }
}
