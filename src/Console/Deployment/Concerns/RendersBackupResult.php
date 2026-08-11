<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment\Concerns;

use JsonException;

trait RendersBackupResult
{
    /** @param array<string, mixed> $result */
    private function renderBackupResult(array $result): void
    {
        if ((bool) $this->option('json')) {
            try {
                $this->output->writeln(json_encode(
                    ['schema' => 1, ...$result],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ));
            } catch (JsonException $exception) {
                $this->components->error($exception->getMessage());
            }

            return;
        }

        $healthy = $result['healthy'] ?? null;

        if ($healthy === false) {
            $this->components->error('Backup health check failed.');
        } else {
            $this->components->info('Backup operation completed.');
        }

        foreach (['backup_id', 'mode', 'healthy', 'duration_seconds', 'deleted_orphan_manifests'] as $key) {
            if (array_key_exists($key, $result)) {
                $this->line(sprintf('%s: %s', str_replace('_', ' ', ucfirst($key)), json_encode($result[$key])));
            }
        }

        foreach (is_array($result['destinations'] ?? null) ? $result['destinations'] : [] as $destination) {
            if (! is_array($destination) || ($destination['healthy'] ?? true) === true) {
                continue;
            }

            $this->line(sprintf('Destination [%s]: unhealthy', (string) ($destination['disk'] ?? 'unknown')));
            $verification = $destination['verification'] ?? null;

            if (is_array($verification) && is_string($verification['error'] ?? null)) {
                $this->line('Reason: '.$verification['error']);
            }
        }
    }
}
