<?php

namespace Native\Desktop\Contracts;

interface ChildProcess
{
    public function get(?string $alias = null): ?self;

    public function all(): array;

    public function start(
        string|array $cmd,
        string $alias,
        ?string $cwd = null,
        ?array $env = null,
        bool $persistent = false,
        bool $handlesOwnShutdown = false
    ): self;

    public function php(string|array $cmd, string $alias, ?array $env = null, ?bool $persistent = false, ?array $iniSettings = null, bool $handlesOwnShutdown = false): self;

    public function artisan(string|array $cmd, string $alias, ?array $env = null, ?bool $persistent = false, ?array $iniSettings = null, bool $handlesOwnShutdown = false): self;

    /**
     * @deprecated v3 removes this, as no Node is bundled there. Start node with start() instead, by its full path or with PATH in env. Nothing changes in v2.
     */
    public function node(string|array $cmd, string $alias, ?array $env = null, ?bool $persistent = false, bool $handlesOwnShutdown = false): self;

    public function stop(?string $alias = null): void;

    public function restart(?string $alias = null): ?self;

    public function message(string $message, ?string $alias = null): self;
}
