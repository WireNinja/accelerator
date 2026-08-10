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
use WireNinja\Accelerator\Deployment\DeploymentOutput;

final class DeployInitCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:deploy:init {--stage=production} {--revision= : Exact Git commit to deploy} {--force}';

    protected $description = 'Provision one VPS stage and deploy its first release';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);

        try {
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $deployer = new Deployer(base_path());
            $deployer->run('accelerator:preflight', $stage, capture: true);

            $revision = $this->option('revision');
            $options = [];

            if (is_string($revision) && $revision !== '') {
                if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) !== 1) {
                    throw new RuntimeException('--revision must be a full Git commit hash.');
                }

                $options[] = "--revision={$revision}";
            }

            if (! $this->confirmed('Initialize', $config)) {
                return self::FAILURE;
            }

            $deployer->run('accelerator:provision', $stage);
            $deployer->run('accelerator:database-init', $stage);
            $deployer->run('accelerator:nightowl-database-init', $stage);
            $deployer->run('deploy', $stage, $options);
            $deployedRevision = DeploymentOutput::markers(
                $deployer->run('accelerator:revision', $stage, capture: true),
                'ACCELERATOR_REVISION',
            )['revision'] ?? null;
            $this->notifyOperation($stage, 'deploy init', 'success', $startedAt, ['revision' => $deployedRevision]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($stage, 'deploy init', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
