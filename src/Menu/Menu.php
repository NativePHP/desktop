<?php

namespace Native\Desktop\Menu;

use Illuminate\Support\Traits\Conditionable;
use JsonSerializable;
use Native\Desktop\Client\Client;
use Native\Desktop\Contracts\MenuItem;

/**
 * @deprecated v3 renames this class to Menu\MenuBuilder. A menu class extends it, and AppShell::menu(), Dock::menu() and a window take it. Nothing changes in v2.
 */
class Menu implements JsonSerializable, MenuItem
{
    use Conditionable;

    protected array $items = [];

    protected string $label = '';

    public function __construct(protected Client $client) {}

    /**
     * @deprecated v3 removes this: set the menu with AppShell::menu(), which v2 does not have yet. Nothing changes in v2.
     */
    public function register(): void
    {
        $items = $this->toArray()['submenu'];

        $this->client->post('menu', [
            'items' => $items,
        ]);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function add(MenuItem $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    public function toArray(): array
    {
        $items = collect($this->items)
            ->map(fn (MenuItem $item) => $item->toArray())
            ->toArray();

        return [
            'label' => $this->label,
            'submenu' => $items,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
