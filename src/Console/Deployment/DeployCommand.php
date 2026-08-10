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

final class DeployCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:deploy {--stage=production} {--revision= : Exact Git commit to deploy} {--force}';

    protected $description = 'Deploy one atomic application release';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);

        try {
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $deployer = new Deployer(base_path());
            $deployer->run('accelerator:preflight', $stage, capture: true);

            if (! $this->confirmed('Deploy', $config)) {
                return self::FAILURE;
            }

            $revision = $this->option('revision');
            $options = [];

            if (is_string($revision) && $revision !== '') {
                if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) !== 1) {
                    throw new RuntimeException('--revision must be a full Git commit hash.');
                }

                $options[] = "--revision={$revision}";
            }

            $deployer->run('deploy', $stage, $options);
            $this->notifyOperation($stage, 'deploy', 'success', $startedAt, ['revision' => is_string($revision) ? $revision : null]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($stage, 'deploy', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
