<?php

namespace Native\Desktop\Contracts;

/**
 * @deprecated v3 removes this interface. A test there swaps the GlobalShortcut\GlobalShortcut class instead. Nothing changes in v2.
 */
interface GlobalShortcut
{
    public function key(string $key): self;

    public function event(string $event): self;

    public function register(): void;

    public function unregister(): void;
}
