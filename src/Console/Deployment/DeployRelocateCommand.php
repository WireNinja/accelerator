<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Console\Deployment\Concerns\NotifiesDeploymentOperation;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final class DeployRelocateCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:deploy:relocate {old-root} {--stage=production} {--force}';

    protected $description = 'Copy an old domain root to the configured root while preserving the old root';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);

        try {
            $oldRoot = rtrim((string) $this->argument('old-root'), '/');
            $config = DeploymentConfig::load(base_path(), $stage);

            if (! str_starts_with($oldRoot, '/var/www/') || $oldRoot === $config->deployRoot) {
                throw new RuntimeException('old-root must be a different explicit path below /var/www.');
            }

            if (! $this->confirmed("Relocate {$oldRoot} to", $config)) {
                return self::FAILURE;
            }

            (new Deployer(base_path()))->run('accelerator:relocate', $stage, ['-o', "old_root={$oldRoot}"]);
            $this->notifyOperation($stage, 'deploy relocate', 'success', $startedAt, ['old_root' => $oldRoot]);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->notifyOperation($stage, 'deploy relocate', 'failed', $startedAt, ['error' => $exception->getMessage()]);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
