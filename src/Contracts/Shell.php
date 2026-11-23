<?php

namespace Native\Desktop\Contracts;

/**
 * @deprecated v3 removes this interface with the Shell facade. Its methods are on AppShell there. Nothing changes in v2.
 */
interface Shell
{
    public function showInFolder(string $path): void;

    public function openFile(string $path): string;

    public function trashFile(string $path): void;

    public function openExternal(string $url): void;
}
