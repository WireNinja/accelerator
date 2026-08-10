<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console\Deployment;

use Illuminate\Console\Command;
use JsonException;
use Throwable;
use WireNinja\Accelerator\Support\Backup\BackupManager;
use WireNinja\Accelerator\Support\Operations\OperatorTelegramNotifier;

final class RuntimeBackupCommand extends Command
{
    protected $signature = 'accelerator:backup:runtime
        {action : create, list, status, verify, cleanup, prepare, discard, post-restore-health, notify-test, or restore notification}
        {--only=all : all, database, or files}
        {--backup= : Exact backup ID}
        {--disk= : Exact configured filesystem disk}
        {--revision= : Revision identity override for a pre-migration backup}
        {--json : Emit JSON without the remote transport marker}';

    protected $description = 'Internal stage runtime for Accelerator backup operations';

    protected $hidden = true;

    public function __construct(
        private readonly BackupManager $backups,
        private readonly OperatorTelegramNotifier $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        try {
            $result = match ($action) {
                'create' => $this->backups->create((string) $this->option('only'), $this->revision()),
                'list' => $this->backups->inventory(),
                'status' => $this->backups->status(verifyNewest: true),
                'verify' => $this->backups->verify($this->backupId(), $this->disk()),
                'cleanup' => $this->backups->cleanup(),
                'prepare' => $this->backups->prepareRestore($this->backupId(), $this->disk(), (string) $this->option('only')),
                'discard' => $this->backups->discardPreparedRestore($this->backupId()),
                'post-restore-health' => $this->backups->postRestoreHealth(),
                'notify-test' => $this->notifyTest(),
                'restore-started' => $this->notifyRestore('started'),
                'restore-succeeded' => $this->notifyRestore('success'),
                'restore-failed' => $this->notifyRestore('failed'),
                default => throw new \RuntimeException("Unknown backup runtime action [{$action}]."),
            };
            $this->writeResult($result);

            return ($result['status'] ?? 'ERROR') === 'OK' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            if ($action === 'verify') {
                $this->notifier->send('backup verification', 'failed', ['error' => $exception->getMessage()]);
            } elseif ($action === 'prepare') {
                $this->notifier->send('backup restore', 'failed', [
                    'backup_id' => is_string($this->option('backup')) ? $this->option('backup') : null,
                    'error' => $exception->getMessage(),
                    'next_command' => 'php artisan accelerator:backup:verify --stage='.config('accelerator.operations.stage'),
                ], force: true);
            }

            $this->writeResult([
                'status' => 'ERROR',
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function notifyTest(): array
    {
        $configured = $this->notifier->configured();
        $delivered = $configured && $this->notifier->send('operator notification test', 'success', force: true);

        return [
            'status' => $delivered ? 'OK' : 'ERROR',
            'configured' => $configured,
            'delivered' => $delivered,
            'error' => $configured ? null : 'ACCELERATOR_TELEGRAM_BOT_TOKEN and ACCELERATOR_TELEGRAM_CHAT_ID are required.',
        ];
    }

    /** @return array<string, mixed> */
    private function notifyRestore(string $result): array
    {
        $delivered = $this->notifier->send('backup restore', $result, [
            'backup_id' => $this->backupId(),
            'next_command' => $result === 'failed'
                ? 'php artisan accelerator:deploy:status --stage='.config('accelerator.operations.stage')
                : null,
        ], force: true);

        return [
            'status' => 'OK',
            'delivered' => $delivered,
            'backup_id' => $this->backupId(),
            'result' => $result,
        ];
    }

    private function backupId(): string
    {
        $backupId = $this->option('backup');

        if (! is_string($backupId) || $backupId === '') {
            throw new \RuntimeException('--backup is required for verification.');
        }

        return $backupId;
    }

    private function disk(): ?string
    {
        $disk = $this->option('disk');

        return is_string($disk) && $disk !== '' ? $disk : null;
    }

    private function revision(): ?string
    {
        $revision = $this->option('revision');

        if (! is_string($revision) || $revision === '') {
            return null;
        }

        if (preg_match('/^[a-f0-9]{40,64}$/i', $revision) !== 1) {
            throw new \RuntimeException('--revision must be a full Git commit hash.');
        }

        return $revision;
    }

    /** @param array<string, mixed> $result */
    private function writeResult(array $result): void
    {
        try {
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $json = json_encode(['status' => 'ERROR', 'error' => $exception->getMessage()]);
        }

        if ($this->option('json')) {
            $this->output->writeln((string) $json);

            return;
        }

        $this->output->writeln('ACCELERATOR_BACKUP_RESULT='.base64_encode((string) $json));
    }
}
