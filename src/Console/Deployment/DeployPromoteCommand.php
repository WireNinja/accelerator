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

final class DeployPromoteCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:deploy:promote {--from=staging} {--to=production} {--force}';

    protected $description = 'Deploy the exact successful source-stage Git revision to another stage';

    public function handle(): int
    {
        $to = (string) $this->option('to');
        $startedAt = microtime(true);

        try {
            $from = (string) $this->option('from');

            if ($from === $to) {
                throw new RuntimeException('Promotion source and target stages must differ.');
            }

            $source = DeploymentConfig::load(base_path(), $from);
            $target = DeploymentConfig::load(base_path(), $to);
            $environment = new DeploymentEnvironment(base_path());
            $environment->assertValid($source);
            $environment->assertValid($target);

            if ($source->repository !== $target->repository) {
                throw new RuntimeException('Promotion stages must use the same repository.');
            }

            $deployer = new Deployer(base_path());
            $revision = DeploymentOutput::markers(
                $deployer->run('accelerator:revision', $from, capture: true),
                'ACCELERATOR_REVISION',
            )['revision'] ?? '';

            if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) !== 1) {
                throw new RuntimeException("{$from} has no valid deployed revision to promote.");
            }

            $deployer->run('accelerator:preflight', $to, capture: true);

            if (! $this->confirmed("Promote {$revision} from {$from} to", $target)) {
                return self::FAILURE;
            }

            $deployer->run('deploy', $to, ["--revision={$revision}"]);
            $deployed = DeploymentOutput::markers(
                $deployer->run('accelerator:revision', $to, capture: true),
                'ACCELERATOR_REVISION',
            )['revision'] ?? '';

            if (! hash_equals($revision, $deployed)) {
                throw new RuntimeException("Promotion verification failed: {$to} runs [{$deployed}] instead of [{$revision}].");
            }

            $this->components->info("Promoted {$revision} from {$from} to {$to}.");
            $this->notifyOperation($to, 'deploy promote', 'success', $startedAt, ['revision' => $revision, 'source_stage' => $from]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($to, 'deploy promote', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
