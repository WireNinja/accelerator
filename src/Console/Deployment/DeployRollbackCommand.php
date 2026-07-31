<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class DeployRollbackCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:deploy:rollback {--stage=production} {--force}';

    protected $description = 'Point current to the previous release without reversing migrations';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);

            if (! $this->confirmed('Rollback symlink for', $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('rollback', $stage);
            $this->components->warn('Release symlink rolled back. Database migrations were not reversed; review schema compatibility manually.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
