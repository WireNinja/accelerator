<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class DeployUnlockCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:deploy:unlock {--stage=production} {--force}';

    protected $description = 'Remove a confirmed stale Deployer lock';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);

            if (! $this->confirmed('Unlock', $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('deploy:unlock', $stage);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
