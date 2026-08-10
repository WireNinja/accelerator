<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\RendersBackupResult;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class BackupListCommand extends Command
{
    use RendersBackupResult;

    protected $signature = 'accelerator:backup:list {--stage=production} {--json}';

    protected $description = 'List stage-owned Accelerator backups';

    public function handle(): int
    {
        try {
            $stage = (string) $this->option('stage');
            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $result = (new RemoteBackup(base_path()))->run($stage, 'list');
            $this->renderBackupResult($result);

            if (! $this->option('json')) {
                foreach ($result['destinations'] ?? [] as $destination) {
                    $this->components->info('Disk: '.($destination['disk'] ?? 'unknown'));
                    $this->table(['Backup ID', 'Created', 'Bytes', 'Revision', 'Manifest'], array_map(
                        static fn (array $backup): array => [
                            $backup['backup_id'] ?? '',
                            $backup['created_at'] ?? '',
                            $backup['size_bytes'] ?? 0,
                            $backup['revision'] ?? 'legacy',
                            ($backup['manifest'] ?? false) ? 'yes' : 'no',
                        ],
                        is_array($destination['backups'] ?? null) ? $destination['backups'] : [],
                    ));
                }
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
