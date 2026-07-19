<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telemetry:prune {--days= : Override the configured retention in days}')]
#[Description('Prune retained Accelerator telemetry occurrences and compact its SQLite database')]
final class TelemetryPruneCommand extends Command
{
    public function handle(TelemetryStore $store): int
    {
        if (! config('accelerator.telemetry.pruning_enabled', true)) {
            $this->components->info('Telemetry pruning is disabled.');

            return self::SUCCESS;
        }

        $days = max(1, (int) ($this->option('days') ?? config('accelerator.telemetry.retention_days', 90)));

        try {
            $deleted = $store->prune($days);
        } catch (Throwable $exception) {
            $this->components->error('Telemetry prune failed: '.SensitiveDataFilter::text($exception->getMessage(), 500));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Pruned %d occurrences, %d empty groups, %d malformed records, and %d delivered notifications older than %d days.',
            $deleted['occurrences'],
            $deleted['groups'],
            $deleted['failures'],
            $deleted['notifications'],
            $days,
        ));

        return self::SUCCESS;
    }
}
