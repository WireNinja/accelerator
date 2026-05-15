<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Ops;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Support\Deployment\DeployConfig;

#[Signature('ops:logs {service=all : Service name or all} {--stage= : Deployment stage}')]
#[Description('Tail deployment service logs for a stage')]
final class LogsCommand extends Command
{
    public function handle(): int
    {
        try {
            $stage = DeployConfig::stage($this->option('stage'));
        } catch (\InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $services = (string) $this->argument('service') === 'all'
            ? $stage['services']
            : [(string) $this->argument('service')];

        $files = collect($services)
            ->map(fn (string $service): string => "{$stage['paths']['shared']}/storage/logs/{$service}.log")
            ->filter(fn (string $path): bool => is_file($path))
            ->values()
            ->all();

        if ($files === []) {
            $this->components->error('No log files found for selected service(s).');

            return self::FAILURE;
        }

        $process = new Process(['tail', '-f', ...$files]);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
