<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;

final class DeploymentOutput
{
    /** @return array<string, string> */
    public static function markers(string $output, string $prefix): array
    {
        $values = [];
        $pattern = '/'.preg_quote($prefix, '/').' ([a-z_]+)=(.*)$/';

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (preg_match($pattern, $line, $matches) !== 1) {
                continue;
            }

            $values[$matches[1]] = trim($matches[2]);
        }

        if ($values === []) {
            throw new RuntimeException("Remote operation returned no {$prefix} fields.");
        }

        return $values;
    }
}
