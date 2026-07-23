<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Configuration;

final class SshConfig
{
    /** @return list<string> */
    public static function aliases(): array
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME');
        $path = is_string($home) ? rtrim($home, '/').'/.ssh/config' : '';
        $contents = $path !== '' && is_file($path) ? file_get_contents($path) : false;

        if (! is_string($contents)) {
            return [];
        }

        preg_match_all('/^\s*Host\s+(.+)$/mi', $contents, $matches);
        $aliases = [];

        foreach ($matches[1] as $hosts) {
            foreach (preg_split('/\s+/', trim((string) $hosts)) ?: [] as $host) {
                if ($host !== '' && ! strpbrk($host, '*?')) {
                    $aliases[$host] = true;
                }
            }
        }

        $aliases = array_keys($aliases);
        sort($aliases);

        return $aliases;
    }
}
