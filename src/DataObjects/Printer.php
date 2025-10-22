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

    public function __get(string $name): mixed
    {
        return match ($name) {
            'status' => $this->handleDeprecatedProperty('status', 0),
            'isDefault' => $this->handleDeprecatedProperty('isDefault', false),
            default => throw new \InvalidArgumentException("Property '{$name}' does not exist on Printer class"),
        };
    }

    private function handleDeprecatedProperty(string $property, mixed $defaultValue): mixed
    {
        logger()->warning("Deprecated: Printer::\${$property} property has been removed in upstream Electron and will no longer be available");

        return $defaultValue;
    }
}
