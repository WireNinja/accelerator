<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Console\Deployment\Concerns\NotifiesDeploymentOperation;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;

final class EnvironmentPushCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:env:push {--stage=production} {--force}';

    protected $description = 'Atomically push the canonical local stage environment and restart that stage';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);

        try {
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);

            if (! $this->confirmed('Push environment and restart', $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('accelerator:environment-push', $stage);
            $this->notifyOperation($stage, 'environment push', 'success', $startedAt);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($stage, 'environment push', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
