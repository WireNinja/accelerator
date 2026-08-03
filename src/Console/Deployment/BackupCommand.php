<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class BackupCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:backup {--stage=production} {--only=all : all, database, or files} {--force}';

    protected $description = 'Run a stage-scoped application backup as the runtime user';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $mode = (string) $this->option('only');
            $config = DeploymentConfig::load(base_path(), $stage);

            if (! in_array($mode, ['all', 'database', 'files'], true)) {
                throw new RuntimeException('--only must be all, database, or files.');
            }

            if (! $this->confirmed("Back up {$mode} data for", $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('accelerator:backup-manual', $stage, ["--backup-mode={$mode}"]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
