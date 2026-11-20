<?php

namespace Native\Desktop\Menu\Items;

/**
 * @deprecated v3 renames this class to Item, which Menu::item() makes. Menu::label() still makes it there. Nothing changes in v2.
 */
class Label extends MenuItem
{
    public function __construct(
        protected ?string $label,
        protected ?string $accelerator = null
    ) {}
}
