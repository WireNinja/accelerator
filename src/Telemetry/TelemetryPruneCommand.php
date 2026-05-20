<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telemetry:prune {--days= : Override retention days from config}')]
#[Description('Prune telemetry exception records older than the configured retention period')]
class TelemetryPruneCommand extends Command
{
    public function handle(TelemetryDatabase $database): int
    {
        if (! config('accelerator.telemetry.pruning_enabled', true)) {
            $this->components->info('Telemetry pruning is disabled via config.');

            return 0;
        }

        $pdo = $database->connection();

        if ($pdo === null) {
            $this->components->error('Telemetry database unavailable.');

            return 1;
        }

        $days = (int) ($this->option('days') ?? config('accelerator.telemetry.retention_days', 90));
        $cutoff = now()->subDays($days)->toIso8601String();

        $this->components->info("Pruning telemetry records older than {$days} days...");

        // Delete old occurrences.
        $occurrenceStmt = $pdo->prepare('DELETE FROM exception_occurrences WHERE created_at < :cutoff');
        $occurrenceStmt->execute(['cutoff' => $cutoff]);
        $deletedOccurrences = $occurrenceStmt->rowCount();

        // Delete groups that have no remaining occurrences.
        $groupStmt = $pdo->prepare(<<<'SQL'
            DELETE FROM exception_groups
            WHERE id NOT IN (SELECT DISTINCT group_id FROM exception_occurrences)
        SQL);
        $groupStmt->execute();
        $deletedGroups = $groupStmt->rowCount();

        $this->components->info("Pruned {$deletedOccurrences} occurrences and {$deletedGroups} empty groups.");

        // Vacuum to reclaim disk space.
        $pdo->exec('VACUUM');

        return 0;
    }
}
