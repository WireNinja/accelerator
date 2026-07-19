<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('telemetry:status {--json : Emit machine-readable JSON}')]
#[Description('Inspect Accelerator telemetry storage, buffer, and notification health')]
final class TelemetryStatusCommand extends Command
{
    public function handle(TelemetryStore $store, TelemetryBuffer $buffer, TelemetryNotifier $notifier): int
    {
        $status = [
            'feature_enabled' => (bool) config('accelerator.features.telemetry', false),
            'capture_runtime' => TelemetryBuffer::supported() ? 'octane-swoole' : 'inactive',
            'database_path' => $store->path(),
            'database_available' => false,
            'store' => null,
            'buffer' => $buffer->health(),
            'notification_channels' => $notifier->channels(),
            'configuration_issues' => $notifier->configurationIssues(),
            'error' => null,
        ];

        try {
            $status['store'] = $store->statistics();
            $status['database_available'] = true;
        } catch (Throwable $exception) {
            $status['error'] = SensitiveDataFilter::text($exception->getMessage(), 500);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->render($status);
        }

        return $status['database_available'] && $status['configuration_issues'] === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function render(array $status): void
    {
        $this->components->twoColumnDetail('Feature', $status['feature_enabled'] ? '<fg=green>enabled</>' : '<fg=yellow>disabled</>');
        $this->components->twoColumnDetail('Capture runtime', (string) $status['capture_runtime']);
        $this->components->twoColumnDetail('Database', $status['database_available'] ? '<fg=green>available</>' : '<fg=red>unavailable</>');
        $this->components->twoColumnDetail('Path', (string) $status['database_path']);
        $channels = is_array($status['notification_channels']) ? $status['notification_channels'] : [];
        $this->components->twoColumnDetail('Channels', $channels === [] ? 'none' : implode(', ', $channels));

        if (is_array($status['store'])) {
            foreach ($status['store'] as $key => $value) {
                $label = ucfirst(str_replace('_', ' ', (string) $key));
                $display = is_int($value) ? number_format($value) : (string) $value;
                $this->components->twoColumnDetail($label, $display);
            }
        }

        foreach (is_array($status['configuration_issues']) ? $status['configuration_issues'] : [] as $issue) {
            $this->components->warn((string) $issue);
        }

        if (is_string($status['error'])) {
            $this->components->error($status['error']);
        }
    }
}
