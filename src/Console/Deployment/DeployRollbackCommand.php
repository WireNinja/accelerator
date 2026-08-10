<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Console\Deployment\Concerns\NotifiesDeploymentOperation;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class DeployRollbackCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:deploy:rollback {--stage=production} {--force}';

    protected $description = 'Point current to the previous release without reversing migrations';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);

        try {
            $config = DeploymentConfig::load(base_path(), $stage);

            if (! $this->confirmed('Rollback symlink for', $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('rollback', $stage);
            $this->components->warn('Release symlink rolled back. Database migrations were not reversed; review schema compatibility manually.');
            $this->notifyOperation($stage, 'deploy rollback', 'success', $startedAt);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($stage, 'deploy rollback', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
