<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class DeployInitCommand extends Command
{
    use ConfirmsDeployment;

    protected $signature = 'accelerator:deploy:init {--stage=production} {--force}';

    protected $description = 'Provision one VPS stage and deploy its first release';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            $deployer = new Deployer(base_path());
            $deployer->run('accelerator:preflight', $stage, capture: true);

            if (! $this->confirmed('Initialize', $config)) {
                return self::FAILURE;
            }

            $deployer->run('accelerator:provision', $stage);
            $deployer->run('deploy', $stage);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
