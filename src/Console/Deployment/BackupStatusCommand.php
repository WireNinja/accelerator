<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\RendersBackupResult;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class BackupStatusCommand extends Command
{
    use RendersBackupResult;

    protected $signature = 'accelerator:backup:status {--stage=production} {--json}';

    protected $description = 'Inspect stage backup health and storage usage';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $result = (new RemoteBackup(base_path()))->run($stage, 'status');
            $this->renderBackupResult($result);

            return ($result['healthy'] ?? false) === true ? self::SUCCESS : self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
