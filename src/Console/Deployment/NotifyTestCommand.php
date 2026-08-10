<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\RendersBackupResult;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class NotifyTestCommand extends Command
{
    use RendersBackupResult;

    protected $signature = 'accelerator:notify:test {--stage=production} {--json}';

    protected $description = 'Send a stage-owned Accelerator operator Telegram test';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $result = (new RemoteBackup(base_path()))->run($stage, 'notify-test');
            $this->renderBackupResult($result);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
