<?php

namespace Native\Desktop;

use Illuminate\Support\Traits\Conditionable;
use Native\Desktop\Client\Client;
use Native\Desktop\Menu\Menu;

class Dock
{
    use Conditionable;

    public function __construct(protected Client $client) {}

    public function menu(Menu $menu)
    {
        $items = $menu->toArray()['submenu'];

        $this->client->post('dock', [
            'items' => $items,
        ]);
    }

    public function show()
    {
        $this->client->post('dock/show');
    }

    public function hide()
    {
        $this->client->post('dock/hide');
    }

    public function icon(string $path)
    {
        $this->client->post('dock/icon', ['path' => $path]);
    }

    public function bounce(string $type = 'informational')
    {
        $this->client->post('dock/bounce', ['type' => $type]);
    }

    public function cancelBounce()
    {
        $this->client->post('dock/cancel-bounce');
    }

    /**
     * @deprecated v3 keeps badge() but it returns nothing, and a call with no label clears the badge, where v2 reads it back. v3 has no way to read the badge. To clear it, badge('') works in v2 and v3. Nothing changes in v2.
     */
    public function badge(?string $label = null): ?string
    {
        if (is_null($label)) {
            return $this->client->get('dock/badge');
        }

        $this->client->post('dock/badge', ['label' => $label]);

        return null;
    }
}
