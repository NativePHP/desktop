<?php

namespace Native\Desktop\Builder\Concerns;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Filesystem\Filesystem;

trait PrunesVendorDirectory
{
    abstract public function buildPath(string $path = ''): string;

    public function pruneVendorDirectory()
    {
        Process::path($this->buildPath('app'))
            ->timeout(300)
            ->run('composer install --no-dev', function (string $type, string $output) {
                echo $output;
            })
            ->throw();

        $filesystem = new Filesystem;
        $filesystem->remove([
            $this->buildPath('app/vendor/bin'),
            $this->buildPath('app/vendor/nativephp/php-bin'),
        ]);

        // Remove the bundled PHP binaries for a custom binary path.
        // They get duplicated into the app's build resources, so
        // the copies left in the source tree are dead weight.
        //
        // We remove only the archives we manage, never the
        // parent directory, so the user's files are kept,
        // and a root path never wipes the app (#115).
        $binaryDirectory = $this->buildPath('app/'.$this->binaryPackageDirectory().'bin');
        $filesystem->remove(glob("{$binaryDirectory}/*/*/php-*.zip"));
    }
}
