<?php

namespace Native\Desktop\Menu;

use Native\Desktop\Client\Client;
use Native\Desktop\Contracts\MenuItem;
use Native\Desktop\Enums\RolesEnum;

class MenuBuilder
{
    public function __construct(protected Client $client) {}

    public function make(MenuItem ...$items): Menu
    {
        $menu = new Menu($this->client);

        foreach ($items as $item) {
            $menu->add($item);
        }

        return $menu;
    }

    /**
     * @deprecated v3 removes this: build the menu with Menu::make() and set it with AppShell::menu(), which v2 does not have yet. Nothing changes in v2.
     */
    public function create(MenuItem ...$items): void
    {
        $this->make(...$items)
            ->register();
    }

    /**
     * @deprecated v3 returns the default menu bar and sets nothing: AppShell::menu(Menu::default()), which v2 does not have yet. Nothing changes in v2.
     */
    public function default(): void
    {
        $this->create(
            $this->app(),
            $this->file(),
            $this->edit(),
            $this->view(),
            $this->window(),
        );
    }

    /**
     * @deprecated v3 keeps label() so v2's menus keep working, but deprecates it for item(), which v2 does not have yet. In v3 the shortcut is set with hotkey(): Menu::item('Save')->hotkey('CmdOrCtrl+S'). Nothing changes in v2.
     */
    public function label(string $label, ?string $hotkey = null): Items\Label
    {
        return new Items\Label($label, $hotkey);
    }

    public function checkbox(string $label, bool $checked = false, ?string $hotkey = null): Items\Checkbox
    {
        return new Items\Checkbox($label, $checked, $hotkey);
    }

    public function radio(string $label, bool $checked = false, ?string $hotkey = null): Items\Radio
    {
        return new Items\Radio($label, $checked, $hotkey);
    }

    /**
     * @deprecated v3 keeps link() but takes the label first, link($label, $url), as v3's notification and alert actions and mobile's Tab::link() do. The label is required there. Nothing changes in v2.
     */
    public function link(string $url, ?string $label = null, ?string $hotkey = null): Items\Link
    {
        return new Items\Link($url, $label, $hotkey);
    }

    /**
     * @deprecated v3 keeps route() but takes the label first, route($label, $route, $parameters), as link() does in v3. The label is required there. Nothing changes in v2.
     */
    public function route(string $route, ?string $label = null, ?string $hotkey = null): Items\Link
    {
        return new Items\Link(route($route), $label, $hotkey);
    }

    public function app(): Items\Role
    {
        return new Items\Role(RolesEnum::APP_MENU);
    }

    public function file(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::FILE_MENU, $label);
    }

    public function edit(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::EDIT_MENU, $label);
    }

    public function view(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::VIEW_MENU, $label);
    }

    public function window(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::WINDOW_MENU, $label);
    }

    public function separator(): Items\Separator
    {
        return new Items\Separator;
    }

    public function fullscreen(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::TOGGLE_FULL_SCREEN, $label);
    }

    /**
     * @deprecated v3 removes this role: its windows have no web view, so there are no developer tools to open. There is no v2 replacement.
     */
    public function devTools(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::TOGGLE_DEV_TOOLS, $label);
    }

    public function undo(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::UNDO, $label);
    }

    public function redo(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::REDO, $label);
    }

    public function cut(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::CUT, $label);
    }

    public function copy(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::COPY, $label);
    }

    public function paste(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::PASTE, $label);
    }

    public function pasteAndMatchStyle(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::PASTE_STYLE, $label);
    }

    /**
     * @deprecated v3 removes this role: its windows have no web view, so there is no page to reload. There is no v2 replacement.
     */
    public function reload(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::RELOAD, $label);
    }

    public function minimize(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::MINIMIZE, $label);
    }

    public function close(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::CLOSE, $label);
    }

    public function quit(?string $label = null): Items\Role
    {
        if (is_null($label)) {
            $label = __('Quit').' '.config('app.name');
        }

        return new Items\Role(RolesEnum::QUIT, $label);
    }

    public function help(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::HELP, $label);
    }

    public function hide(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::HIDE, $label);
    }

    public function about(?string $label = null): Items\Role
    {
        return new Items\Role(RolesEnum::ABOUT, $label);
    }
}
