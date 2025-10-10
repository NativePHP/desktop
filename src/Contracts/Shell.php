<?php

namespace Native\Desktop\Contracts;

interface Shell
{
    public function showInFolder(string $path): void;

    public function openFile(string $path): string;

    public function trashFile(string $path): void;

    public function openExternal(string $url): void;
}
