<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Console\Deployment\Concerns\RendersBackupResult;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class BackupCleanupCommand extends Command
{
    use ConfirmsDeployment;
    use RendersBackupResult;

    protected $signature = 'accelerator:backup:cleanup {--stage=production} {--force} {--json}';

    protected $description = 'Apply stage backup retention and remove orphan manifests';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);

            if (! $this->confirmed('Clean retained backups for', $config)) {
                return self::FAILURE;
            }

            $result = (new RemoteBackup(base_path()))->run($stage, 'cleanup');
            $this->renderBackupResult($result);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
