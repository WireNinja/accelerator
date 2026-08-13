<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

final readonly class BackupRestorer
{
    public function __construct(
        private BackupManager $backups,
        private BackupArchive $archiveInspector,
    ) {}

    /** @return array<string, mixed> */
    public function prepare(string $backupId, ?string $requestedDisk = null, string $mode = 'all'): array
    {
        if (! in_array($mode, ['all', 'database', 'files'], true)) {
            throw new RuntimeException('Restore mode must be all, database, or files.');
        }

        $verified = $this->backups->verify($backupId, $requestedDisk);
        $manifest = $verified['manifest'];

        if (! is_array($manifest)) {
            throw new RuntimeException("Backup [{$backupId}] has no Accelerator manifest.");
        }

        foreach ([
            'deployment_key' => (string) config('accelerator.operations.deployment_key'),
            'stage' => (string) config('accelerator.operations.stage'),
        ] as $key => $expected) {
            if (($manifest[$key] ?? null) !== $expected) {
                throw new RuntimeException("Backup [{$backupId}] {$key} does not match this runtime.");
            }
        }

        $revision = is_file(base_path('REVISION')) ? trim((string) file_get_contents(base_path('REVISION'))) : '';

        if ($revision === '' || ($manifest['revision'] ?? null) !== $revision) {
            throw new RuntimeException(sprintf(
                'Backup [%s] requires active revision [%s], current [%s].',
                $backupId,
                $manifest['revision'] ?? 'unknown',
                $revision ?: 'unknown',
            ));
        }

        $backupMode = (string) ($manifest['mode'] ?? '');

        if (($mode === 'database' && $backupMode === 'files') || ($mode === 'files' && $backupMode === 'database')) {
            throw new RuntimeException("Backup [{$backupId}] does not contain the requested {$mode} data.");
        }

        $directory = storage_path("framework/accelerator-restore/{$backupId}");
        $availableBytes = disk_free_space(storage_path());
        $requiredBytes = ((int) ($verified['size_bytes'] ?? 0) * 2) + (100 * 1024 * 1024);

        if (! is_float($availableBytes) || $availableBytes < $requiredBytes) {
            throw new RuntimeException("Backup [{$backupId}] cannot be prepared because local free disk space is insufficient.");
        }

        File::deleteDirectory($directory);

        if (! File::makeDirectory($directory, 0700, true)) {
            throw new RuntimeException('Unable to create the restore preparation directory.');
        }

        $archivePath = $directory.'/backup.zip';
        $disk = Storage::disk((string) $verified['disk']);
        $input = $disk->readStream((string) $verified['path']);
        $output = fopen($archivePath, 'wb');

        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException("Unable to materialize backup [{$backupId}] for restoration.");
        }

        try {
            stream_copy_to_stream($input, $output);
        } finally {
            fclose($input);
            fclose($output);
        }

        $extractedPath = $directory.'/extracted';
        File::makeDirectory($extractedPath, 0700, true);
        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException("Backup [{$backupId}] cannot be opened for restoration.");
        }

        try {
            $password = config('backup.backup.password');

            if (is_string($password) && $password !== '') {
                $archive->setPassword($password);
            }

            $this->archiveInspector->assertSafe($archive, $backupId);

            if (! $archive->extractTo($extractedPath)) {
                throw new RuntimeException("Backup [{$backupId}] could not be extracted.");
            }
        } finally {
            $archive->close();
        }

        $databaseDump = $this->archiveInspector->findDatabaseDump($extractedPath);
        $filesPath = $extractedPath.'/storage/app';

        if (in_array($mode, ['all', 'database'], true) && $databaseDump === null) {
            throw new RuntimeException("Backup [{$backupId}] contains no database dump.");
        }

        if (in_array($mode, ['all', 'files'], true) && ! is_dir($filesPath)) {
            throw new RuntimeException("Backup [{$backupId}] contains no storage/app file tree.");
        }

        return [
            'status' => 'OK',
            'backup_id' => $backupId,
            'mode' => $mode,
            'prepared_path' => $directory,
            'database_dump' => $databaseDump,
            'files_path' => is_dir($filesPath) ? $filesPath : null,
            'manifest' => $manifest,
        ];
    }

    /** @return array<string, mixed> */
    public function discard(string $backupId): array
    {
        $this->assertBackupId($backupId);
        $directory = storage_path("framework/accelerator-restore/{$backupId}");
        File::deleteDirectory($directory);

        return [
            'status' => 'OK',
            'backup_id' => $backupId,
            'discarded' => ! is_dir($directory),
        ];
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        DB::connection()->getPdo();
        $storage = storage_path('app');

        if (! is_dir($storage) || ! is_readable($storage) || ! is_writable($storage)) {
            throw new RuntimeException('Restored storage/app is not readable and writable by the Laravel runtime.');
        }

        return [
            'status' => 'OK',
            'database' => 'connected',
            'storage' => 'readable-writable',
        ];
    }

    private function assertBackupId(string $backupId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $backupId) !== 1) {
            throw new RuntimeException('Backup ID contains unsafe characters.');
        }
    }
}
