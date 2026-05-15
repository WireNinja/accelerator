<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:status {--stage= : Deployment stage}')]
#[Description('Show deployment status for a stage')]
final class StatusCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $paths = $stage['paths'];

        $this->table(['Key', 'Value'], [
            ['Stage', $stage['name']],
            ['Domain', $stage['domain'] ?: '-'],
            ['Root', $paths['root']],
            ['Current', is_link($paths['current']) ? readlink($paths['current']) : 'missing'],
            ['Shared .env', is_file("{$paths['shared']}/.env") ? 'present' : 'missing'],
            ['Supervisor Group', $stage['group']],
            ['Enabled Services', implode(', ', $stage['services']) ?: '-'],
        ]);

        $this->line($this->runProcess(['sudo', 'supervisorctl', 'status', "{$stage['group']}:*"]));

        return self::SUCCESS;
    }

    /**
     * @param list<string> $command
     */
    private function runProcess(array $command): string
    {
        $process = new Process($command);
        $process->run();

        return trim($process->getOutput() ?: $process->getErrorOutput());
    }
}
