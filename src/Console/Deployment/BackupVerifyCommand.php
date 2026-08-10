<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\RendersBackupResult;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class BackupVerifyCommand extends Command
{
    use RendersBackupResult;

    protected $signature = 'accelerator:backup:verify {--stage=production} {--backup= : Exact backup ID} {--disk=} {--json}';

    protected $description = 'Verify a stage-owned backup checksum and archive';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $backupId = (string) $this->option('backup');

            if ($backupId === '') {
                throw new RuntimeException('--backup is required.');
            }

            $options = ["--backup-id={$backupId}"];
            $disk = $this->option('disk');

            if (is_string($disk) && $disk !== '') {
                $options[] = "--backup-disk={$disk}";
            }

            $result = (new RemoteBackup(base_path()))->run($stage, 'verify', $options);
            $this->renderBackupResult($result);

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
