<?php

namespace Native\Desktop\Events\App;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated v3 moves this event to AppShell\Events\ApplicationBooted. It fires once there, on a cold start, and not again on a Dock click with no window open. Nothing changes in v2.
 */
class ApplicationBooted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct() {}
}
