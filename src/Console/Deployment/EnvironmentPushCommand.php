<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;

final class EnvironmentPushCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:env:push {--stage=production} {--force}';

    protected $description = 'Atomically push the canonical local stage environment and restart that stage';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);

            if (! $this->confirmed('Push environment and restart', $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('accelerator:environment-push', $stage);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
