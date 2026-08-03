<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;

final class EnvironmentEditCommand extends Command
{
    protected $signature = 'accelerator:env:edit {--stage=production}';

    protected $description = 'Edit and validate one canonical local stage environment';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage, validateRuntime: false);
            $editor = trim((string) (getenv('VISUAL') ?: getenv('EDITOR') ?: 'vi'));
            $arguments = str_getcsv($editor, ' ', '"', '\\');
            $process = new Process([
                ...array_values(array_filter($arguments, static fn (string $argument): bool => $argument !== '')),
                $config->runtimeEnvironmentFile(),
            ], base_path());
            $process->setTimeout(null);
            $process->setTty(Process::isTtySupported());
            $process->mustRun();
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $this->components->info("{$stage} environment saved and validated.");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
