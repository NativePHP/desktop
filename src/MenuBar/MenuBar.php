<?php

namespace Native\Desktop\MenuBar;

use Native\Desktop\Client\Client;
use Native\Desktop\Concerns\HasDimensions;
use Native\Desktop\Concerns\HasPositioner;
use Native\Desktop\Concerns\HasUrl;
use Native\Desktop\Concerns\HasVibrancy;
use Native\Desktop\Menu\Menu;

class MenuBar
{
    use HasDimensions {
        position as private dimensionsPosition;
    }
    use HasPositioner;
    use HasUrl;
    use HasVibrancy;

    protected string $icon = '';

    protected string $label = '';

    protected string $tooltip = '';

    protected bool $resizable = true;

    protected bool $onlyShowContextMenu = false;

    protected ?Menu $contextMenu = null;

    protected bool $alwaysOnTop = false;

    protected bool $showDockIcon = false;

    protected bool $showOnAllWorkspaces = false;

    protected array $webPreferences = [];

    protected Client $client;

    public function __construct()
    {
        $this->url = url('/');
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @deprecated v3 removes this: an item that has only a context menu opens it on a left click as well, so leave this call out. Nothing changes in v2.
     */
    public function onlyShowContextMenu(bool $onlyContextMenu = true): self
    {
        $this->onlyShowContextMenu = $onlyContextMenu;

        return $this;
    }

    /**
     * @deprecated v3 removes this, and creating a menu bar item no longer hides the Dock icon. The 'dock' key of v3's config sets it: always, never, or with-windows. Nothing changes in v2.
     */
    public function showDockIcon($value = true): self
    {
        $this->showDockIcon = $value;

        return $this;
    }

    public function label(string $label = ''): self
    {
        $this->label = $label;

        return $this;
    }

    public function tooltip(string $tooltip = ''): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function resizable(bool $resizable = true): static
    {
        $this->resizable = $resizable;

        return $this;
    }

    public function alwaysOnTop($alwaysOnTop = true): self
    {
        $this->alwaysOnTop = $alwaysOnTop;

        return $this;
    }

    public function showOnAllWorkspaces($showOnAllWorkspaces = true): self
    {
        $this->showOnAllWorkspaces = $showOnAllWorkspaces;

        return $this;
    }

    /**
     * @deprecated v3 removes this for contextMenu(), which v2's MenuBar facade already has at runtime. Nothing changes in v2.
     */
    public function withContextMenu(Menu $menu): self
    {
        $this->contextMenu = $menu;

        return $this;
    }

    /**
     * @deprecated v3 removes this, as the panel of a v3 menu bar item has no web view. Nothing changes in v2.
     */
    public function webPreferences(array $preferences): static
    {
        $this->webPreferences = $preferences;

        return $this;
    }

    /**
     * @deprecated v3 removes this for a menu bar item, as its panel always sits under its icon. A window keeps position(). Nothing changes in v2.
     */
    public function position($x, $y): self
    {
        return $this->dimensionsPosition($x, $y);
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'icon' => $this->icon,
            'windowPosition' => $this->windowPosition,
            'x' => $this->x,
            'y' => $this->y,
            'label' => $this->label,
            'tooltip' => $this->tooltip,
            'resizable' => $this->resizable,
            'width' => $this->width,
            'height' => $this->height,
            'minWidth' => $this->minWidth,
            'minHeight' => $this->minHeight,
            'maxWidth' => $this->maxWidth,
            'maxHeight' => $this->maxHeight,
            'vibrancy' => $this->vibrancy,
            'showDockIcon' => $this->showDockIcon,
            'transparency' => $this->transparent,
            'backgroundColor' => $this->backgroundColor,
            'onlyShowContextMenu' => $this->onlyShowContextMenu,
            'contextMenu' => ! is_null($this->contextMenu) ? $this->contextMenu->toArray()['submenu'] : null,
            'alwaysOnTop' => $this->alwaysOnTop,
            'showOnAllWorkspaces' => $this->showOnAllWorkspaces,
            'webPreferences' => $this->webPreferences,
        ];
    }
}
