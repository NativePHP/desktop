<?php

namespace Native\Desktop\Builder;

use Native\Desktop\Builder\Concerns\CopiesBundleToBuildDirectory;
use Native\Desktop\Builder\Concerns\CopiesCertificateAuthority;
use Native\Desktop\Builder\Concerns\CopiesToBuildDirectory;
use Native\Desktop\Builder\Concerns\HasPreAndPostProcessing;
use Native\Desktop\Builder\Concerns\LocatesPhpBinary;
use Native\Desktop\Builder\Concerns\ManagesEnvFile;
use Native\Desktop\Builder\Concerns\PrunesVendorDirectory;
use Symfony\Component\Filesystem\Path;

class Builder
{
    use CopiesBundleToBuildDirectory;
    use CopiesCertificateAuthority;
    use CopiesToBuildDirectory;
    use HasPreAndPostProcessing;
    use LocatesPhpBinary;
    use ManagesEnvFile;
    use PrunesVendorDirectory;

    public function __construct(
        private ?string $buildPath = null,
        private ?string $sourcePath = null,
    ) {
        $this->buildPath = $buildPath ?? base_path('build');
        $this->sourcePath = $sourcePath ?? base_path();
    }

    public static function make(
        ?string $buildPath = null,
        ?string $sourcePath = null
    ) {
        return new self($buildPath, $sourcePath);
    }

    public function buildPath(string $path = ''): string
    {
        return Path::join($this->buildPath, $path);
    }

    public function sourcePath(string $path = ''): string
    {
        return Path::join($this->sourcePath, $path);
    }
}
