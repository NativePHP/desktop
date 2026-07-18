<?php

namespace Native\Desktop\Support;

final class LinuxDevelopmentDesktop
{
    public static function name(string $appId, string $appPath, string $operatingSystem = PHP_OS_FAMILY): ?string
    {
        if ($operatingSystem !== 'Linux') {
            return null;
        }

        $segments = array_filter(explode('.', preg_replace('/[^A-Za-z0-9._-]/', '-', $appId)));
        $segments = array_map(
            fn (string $segment) => preg_match('/^[0-9]/', $segment) ? "_{$segment}" : $segment,
            $segments,
        );
        $identifier = implode('.', $segments) ?: 'com.nativephp.app';
        $projectHash = substr(hash('sha256', $appPath), 0, 12);

        return "{$identifier}.nativephp-dev-{$projectHash}";
    }
}
