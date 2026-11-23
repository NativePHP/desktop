<?php

namespace Native\Desktop\Concerns;

trait HasUrl
{
    protected string $url = '';

    /**
     * @deprecated v3 keeps url() on a menu bar item but takes a path of the app, such as url('/tray'), and throws for a full address, such as one from Laravel's url() or route() helpers. route() with a route name works in v2 and v3. Nothing changes in v2.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function route(string $route, array $parameters = []): self
    {
        $this->url(route($route, $parameters));

        return $this;
    }
}
