<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Console\Deployment\Concerns\ConfirmsDeployment;
use WireNinja\Accelerator\Console\Deployment\Concerns\NotifiesDeploymentOperation;
use WireNinja\Accelerator\Deployment\Deployer;
use WireNinja\Accelerator\Deployment\DeploymentConfig;
use WireNinja\Accelerator\Deployment\DeploymentEnvironment;
use WireNinja\Accelerator\Deployment\RemoteBackup;

final class BackupRestoreCommand extends Command
{
    use ConfirmsDeployment;
    use NotifiesDeploymentOperation;

    protected $signature = 'accelerator:backup:restore
        {--stage=production}
        {--backup= : Exact backup ID}
        {--only=all : all, database, or files}
        {--disk= : Exact configured backup disk}
        {--force}
        {--json}';

    protected $description = 'Restore one exact stage-owned backup after destructive safety checks';

    public function handle(): int
    {
        $stage = (string) $this->option('stage');
        $startedAt = microtime(true);
        $restoreDelegated = false;

        try {
            $backupId = (string) $this->option('backup');
            $mode = (string) $this->option('only');
            $disk = $this->option('disk');

            if ($backupId === '') {
                throw new RuntimeException('--backup is required. Restore never guesses the latest backup.');
            }

            if (! in_array($mode, ['all', 'database', 'files'], true)) {
                throw new RuntimeException('--only must be all, database, or files.');
            }

            $config = DeploymentConfig::load(base_path(), $stage);
            (new DeploymentEnvironment(base_path()))->assertValid($config);
            $deployer = new Deployer(base_path());
            $deployer->run('accelerator:preflight', $stage, capture: true);
            $verifyOptions = ["--backup-id={$backupId}"];

            if (is_string($disk) && $disk !== '') {
                $verifyOptions[] = "--backup-disk={$disk}";
            }

            $verified = (new RemoteBackup(base_path()))->run($stage, 'verify', $verifyOptions);
            $manifest = is_array($verified['manifest'] ?? null) ? $verified['manifest'] : [];
            $revisionOutput = $deployer->run('accelerator:revision', $stage, capture: true);

            if (preg_match('/ACCELERATOR_REVISION revision=([a-f0-9]{40,64})/i', $revisionOutput, $matches) !== 1) {
                throw new RuntimeException('Unable to resolve the active successful release revision.');
            }

            $activeRevision = $matches[1];

            foreach ([
                'deployment_key' => $config->deploymentKey,
                'stage' => $stage,
                'revision' => $activeRevision,
            ] as $key => $expected) {
                if (($manifest[$key] ?? null) !== $expected) {
                    throw new RuntimeException("Backup {$key} does not match the selected target [{$expected}].");
                }
            }

            if (! $this->option('json')) {
                $this->table(['Restore field', 'Exact value'], [
                    ['SSH host', $config->sshHost],
                    ['Deployment key', $config->deploymentKey],
                    ['Stage', $stage],
                    ['Domain', $config->domain],
                    ['Stable root', $config->deployRoot],
                    ['Supervisor group', $config->group],
                    ['Backup ID', $backupId],
                    ['Backup disk', $verified['disk'] ?? 'unknown'],
                    ['Backup created', $manifest['created_at'] ?? 'unknown'],
                    ['Database', is_array($manifest['database'] ?? null) ? implode(':', array_filter([
                        $manifest['database']['driver'] ?? null,
                        $manifest['database']['database'] ?? null,
                    ], is_scalar(...))) : 'unknown'],
                    ['Mutable paths', is_array($manifest['include'] ?? null) ? implode(', ', array_filter($manifest['include'], is_string(...))) : 'unknown'],
                    ['Active revision', $activeRevision],
                    ['Backup revision', $manifest['revision']],
                    ['Restore content', $mode],
                ]);
            }

            if (! $this->confirmed("Restore {$mode} from backup {$backupId} into", $config)) {
                return self::FAILURE;
            }

            $options = ["--backup-id={$backupId}", "--backup-mode={$mode}"];

            if (is_string($disk) && $disk !== '') {
                $options[] = "--backup-disk={$disk}";
            }

            $restoreDelegated = true;
            $deployer->run('accelerator:backup-restore', $stage, $options);

            if ($this->option('json')) {
                $this->writeJson([
                    'schema' => 1,
                    'status' => 'OK',
                    'stage' => $stage,
                    'backup_id' => $backupId,
                    'mode' => $mode,
                    'revision' => $activeRevision,
                ]);
            } else {
                $this->components->success("Backup [{$backupId}] restored to {$stage}.");
            }

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            if (! $restoreDelegated) {
                $this->notifyOperation($stage, 'backup restore', 'failed', $startedAt, [
                    'backup_id' => (string) $this->option('backup'),
                    'error' => $exception->getMessage(),
                ], force: true);
            }

            if ($this->option('json')) {
                $this->writeJson(['schema' => 1, 'status' => 'ERROR', 'error' => $exception->getMessage()]);
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }
    }

    /** @param array<string, mixed> $payload */
    private function writeJson(array $payload): void
    {
        try {
            $this->output->writeln(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            $this->components->error($exception->getMessage());
        }
    }
}
