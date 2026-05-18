<?php

namespace WireNinja\Accelerator\Console\Shield;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Console\Concerns\HasBanner;

#[Signature('shield:safe-regenerate
    {--panel=admin : Filament panel ID to regenerate policies for}
    {--json : Output as JSON}
    {--compact : Compact JSON output}')]
#[Description('Safe regenerate shield policies and permissions for a panel')]
class SafeRegenerateCommand extends Command
{
    use HasBanner;

    public function handle(): int
    {
        $panel = (string) $this->option('panel');
        $isJson = (bool) $this->option('json');

        if (! $isJson) {
            $this->displayBanner();
            $this->components->info(sprintf('Regenerating shield policies and permissions safely for panel [%s]...', $panel));
        }

        $exitCode = $this->call('shield:generate', [
            '--all' => true,
            '--option' => 'policies_and_permissions',
            '--ignore-existing-policies' => true,
            '--panel' => $panel,
        ]);

        if ($isJson) {
            $payload = [
                'status' => $exitCode === 0 ? 'OK' : 'ERROR',
                'panel' => $panel,
                'shield_exit_code' => $exitCode,
            ];

            $flags = ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $this->output->writeln(json_encode($payload, $flags));

            return $exitCode === 0 ? 0 : 1;
        }

        if ($exitCode !== 0) {
            $this->components->error(sprintf('Shield regeneration failed for panel [%s] (exit %d).', $panel, $exitCode));

            return 1;
        }

        $this->components->success(sprintf('Shield regeneration complete for panel [%s].', $panel));

        return 0;
    }
}
