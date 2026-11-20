<?php

namespace Native\Desktop\Contracts;

/**
 * @deprecated v3 removes this interface. Every menu item there extends the class Menu\Items\MenuItem. Nothing changes in v2.
 */
interface MenuItem
{
    public function toArray(): array;
}
