<?php

namespace Native\Desktop;

use Native\Desktop\Client\Client;
use Illuminate\Support\Traits\Conditionable;

class AutoUpdater
{
    use Conditionable;

    public function __construct(protected Client $client) {}

    public function checkForUpdates(): void
    {
        $this->client->post('auto-updater/check-for-updates');
    }

    public function quitAndInstall(): void
    {
        $this->client->post('auto-updater/quit-and-install');
    }

    public function downloadUpdate(): void
    {
        $this->client->post('auto-updater/download-update');
    }
}
