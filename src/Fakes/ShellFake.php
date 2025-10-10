<?php

namespace Native\Desktop\Fakes;

use Native\Desktop\Contracts\Shell as ShellContract;
use PHPUnit\Framework\Assert as PHPUnit;

class ShellFake implements ShellContract
{
    /**
     * @var array<int, string|null>
     */
    public array $showInFolderCalls = [];

    /**
     * @var array<int, string|null>
     */
    public array $openFileCalls = [];

    /**
     * @var array<int, string|null>
     */
    public array $trashFileCalls = [];

    /**
     * @var array<int, string|null>
     */
    public array $openExternalCalls = [];

    public function showInFolder(string $path): void
    {
        $this->showInFolderCalls[] = $path;
    }

    public function openFile(string $path): string
    {
        $this->openFileCalls[] = $path;

        return '';
    }

    public function trashFile(string $path): void
    {
        $this->trashFileCalls[] = $path;
    }

    public function openExternal(string $url): void
    {
        $this->openExternalCalls[] = $url;
    }

    public function assertShowInFolder(string $path): void
    {
        PHPUnit::assertContains($path, $this->showInFolderCalls);

    }

    public function assertOpenedFile(string $path): void
    {
        PHPUnit::assertContains($path, $this->openFileCalls);

    }

    public function assertTrashedFile(string $path): void
    {
        PHPUnit::assertContains($path, $this->trashFileCalls);

    }

    public function assertOpenedExternal(string $url): void
    {
        PHPUnit::assertContains($url, $this->openExternalCalls);

    }
}
