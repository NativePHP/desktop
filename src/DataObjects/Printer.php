<?php

namespace Native\Desktop\DataObjects;

class Printer
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $description,
        public array $options
    ) {}
}
