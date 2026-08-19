<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Backup;

use Carbon\CarbonImmutable;
use Composer\InstalledVersions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;
use WireNinja\Accelerator\Support\Cast;
use WireNinja\Accelerator\Support\Operations\OperatorTelegramNotifier;
use ZipArchive;

final readonly class BackupManager
{
    public function __construct(
        private OperatorTelegramNotifier $notifier,
        private BackupArchive $archiveInspector,
    ) {}

    /** @return array<string, mixed> */
    public function create(string $mode, ?string $revision = null): array
    {
        $this->assertEnabled();

        if (! in_array($mode, ['all', 'database', 'files'], true)) {
            throw new RuntimeException('Backup mode must be all, database, or files.');
        }

        $backupId = now('UTC')->format('Y-m-d-H-i-s').'-'.Str::lower((string) Str::ulid());
        $filename = "{$backupId}.zip";
        $arguments = [
            '--filename' => $filename,
            '--disable-notifications' => true,
            '--no-interaction' => true,
        ];

        if ($mode === 'database') {
            $arguments['--only-db'] = true;
        } elseif ($mode === 'files') {
            $arguments['--only-files'] = true;
        }

        $startedAt = microtime(true);

        try {
            if (Artisan::call('backup:run', $arguments) !== 0) {
                throw new RuntimeException(Str::limit(trim(Artisan::output()), 1000));
            }

            $backups = [];

            foreach ($this->disks() as $diskName) {
                $path = $this->findPath($diskName, $filename);
                $manifest = $this->manifest($backupId, $path, $diskName, $mode, $revision);
                $this->writeManifest($diskName, $path, $manifest);
                $this->protectArtifact($diskName, $path);
                $backups[] = $this->verify($backupId, $diskName);
            }

            $this->notifier->send('backup', 'success', [
                'backup_id' => $backupId,
                'mode' => $mode,
                'duration_seconds' => round(microtime(true) - $startedAt, 2),
            ]);
            $this->recordLifecycleState('success', $backupId, null);

            return [
                'status' => 'OK',
                'backup_id' => $backupId,
                'mode' => $mode,
                'duration_seconds' => round(microtime(true) - $startedAt, 2),
                'destinations' => $backups,
            ];
        } catch (Throwable $exception) {
            $this->recordLifecycleState('failed', null, Str::limit($exception->getMessage(), 800));
            $this->notifier->send('backup', 'failed', [
                'mode' => $mode,
                'error' => Str::limit($exception->getMessage(), 800),
            ]);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    public function inventory(): array
    {
        $destinations = [];

        foreach ($this->disks() as $diskName) {
            $disk = Storage::disk($diskName);
            $backups = [];

            foreach ($disk->allFiles($this->backupName()) as $path) {
                if (! str_ends_with($path, '.zip')) {
                    continue;
                }

                $manifest = $this->readManifest($disk, $path);
                $backups[] = [
                    'backup_id' => is_array($manifest) ? ($manifest['backup_id'] ?? pathinfo($path, PATHINFO_FILENAME)) : pathinfo($path, PATHINFO_FILENAME),
                    'disk' => $diskName,
                    'path' => $path,
                    'size_bytes' => $disk->size($path),
                    'created_at' => is_array($manifest) ? ($manifest['created_at'] ?? null) : date(DATE_ATOM, $disk->lastModified($path)),
                    'revision' => is_array($manifest) ? ($manifest['revision'] ?? null) : null,
                    'mode' => is_array($manifest) ? ($manifest['mode'] ?? null) : null,
                    'encrypted' => is_array($manifest) ? ($manifest['encrypted'] ?? null) : null,
                    'manifest' => is_array($manifest),
                ];
            }

            usort(
                $backups,
                static fn (array $left, array $right): int => CarbonImmutable::parse(Cast::mustString($right['created_at'] ?? null))->getTimestamp()
                    <=> CarbonImmutable::parse(Cast::mustString($left['created_at'] ?? null))->getTimestamp(),
            );
            $destinations[] = [
                'disk' => $diskName,
                'backups' => $backups,
            ];
        }

        return [
            'status' => 'OK',
            'backup_name' => $this->backupName(),
            'destinations' => $destinations,
        ];
    }

    /** @return array<string, mixed> */
    public function status(bool $verifyNewest = false): array
    {
        $monitorHealthy = Artisan::call('backup:monitor', [
            '--no-interaction' => true,
        ]) === 0;
        $inventory = $this->inventory();
        $maximumAge = Cast::mustInt(config('accelerator.backup.maximum_age_days', 2));
        $maximumStorage = Cast::mustInt(config('accelerator.backup.maximum_storage_megabytes', 5000)) * 1024 * 1024;
        $healthy = $monitorHealthy;
        $destinations = [];
        $availableBytes = disk_free_space(storage_path());
        $diskHealthy = is_float($availableBytes) && $availableBytes >= 512 * 1024 * 1024;

        foreach (Arr::wrap($inventory['destinations'] ?? null) as $destination) {
            if (! is_array($destination)) {
                continue;
            }

            $backups = array_values(array_filter(Arr::wrap($destination['backups'] ?? null), is_array(...)));
            $newest = $backups[0] ?? null;
            $totalSize = array_sum(array_column($backups, 'size_bytes'));
            $ageDays = is_array($newest) && is_string($newest['created_at'] ?? null)
                ? (int) floor(abs(now()->diffInDays($newest['created_at'])))
                : null;
            $destinationHealthy = is_array($newest)
                && ($newest['manifest'] ?? false) === true
                && $ageDays !== null
                && $ageDays <= $maximumAge
                && $totalSize <= $maximumStorage;
            $verification = null;

            if ($verifyNewest && is_array($newest) && is_string($newest['backup_id'] ?? null)) {
                try {
                    $verification = $this->verify($newest['backup_id'], Cast::mustString($destination['disk'] ?? null));
                } catch (Throwable $exception) {
                    $destinationHealthy = false;
                    $verification = ['status' => 'ERROR', 'error' => Str::limit($exception->getMessage(), 800)];
                }
            }

            $healthy = $healthy && $destinationHealthy;
            $destinations[] = [
                'disk' => Cast::mustString($destination['disk'] ?? null),
                'healthy' => $destinationHealthy,
                'backup_count' => count($backups),
                'total_size_bytes' => $totalSize,
                'newest_backup' => $newest,
                'age_days' => $ageDays,
                'verification' => $verification,
            ];
        }

        $healthy = $healthy && $diskHealthy;

        if (! $healthy) {
            $this->notifier->send('backup health', 'failed', [
                'next_command' => 'easyploy backup status --stage='.Cast::mustString(config('accelerator.operations.stage')).' --json',
            ]);
        }

        return [
            'status' => $healthy ? 'OK' : 'ERROR',
            'healthy' => $healthy,
            'maximum_age_days' => $maximumAge,
            'maximum_storage_megabytes' => Cast::mustInt(config('accelerator.backup.maximum_storage_megabytes', 5000)),
            'available_local_bytes' => is_float($availableBytes) ? (int) $availableBytes : null,
            'local_disk_healthy' => $diskHealthy,
            'last_lifecycle_result' => $this->lifecycleState(),
            'destinations' => $destinations,
        ];
    }

    /** @return array<string, mixed> */
    public function verify(string $backupId, ?string $requestedDisk = null): array
    {
        $this->assertBackupId($backupId);
        $diskName = $requestedDisk ?? $this->disks()[0];
        $disk = Storage::disk($diskName);
        $path = $this->findPath($diskName, "{$backupId}.zip");
        $manifest = $this->readManifest($disk, $path);

        if (! is_array($manifest)) {
            throw new RuntimeException("Backup [{$backupId}] has no Accelerator manifest and cannot be restored automatically.");
        }

        $temporary = tempnam(sys_get_temp_dir(), 'accelerator-backup-');

        if (! is_string($temporary)) {
            throw new RuntimeException('Unable to allocate a temporary backup verification file.');
        }

        try {
            $input = $disk->readStream($path);
            $output = fopen($temporary, 'wb');

            if (! is_resource($input) || ! is_resource($output)) {
                throw new RuntimeException("Unable to read backup [{$backupId}].");
            }

            try {
                stream_copy_to_stream($input, $output);
            } finally {
                fclose($input);
                fclose($output);
            }

            $checksum = hash_file('sha256', $temporary);

            if (! is_string($checksum) || ! hash_equals(Cast::mustString($manifest['sha256'] ?? ''), $checksum)) {
                throw new RuntimeException("Backup [{$backupId}] checksum does not match its manifest.");
            }

            $archive = new ZipArchive;

            if ($archive->open($temporary) !== true) {
                throw new RuntimeException("Backup [{$backupId}] is not a readable ZIP archive.");
            }

            try {
                $password = config('backup.backup.password');

                if (is_string($password) && $password !== '') {
                    $archive->setPassword($password);
                }

                if ($archive->numFiles < 1) {
                    throw new RuntimeException("Backup [{$backupId}] is empty.");
                }

                $this->archiveInspector->assertSafe($archive, $backupId);
                $hasDatabase = false;
                $hasFiles = false;

                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $entry = $archive->getNameIndex($index);

                    if (! is_string($entry)) {
                        throw new RuntimeException("Backup [{$backupId}] contains an unreadable ZIP entry.");
                    }

                    $normalized = ltrim(str_replace('\\', '/', $entry), '/');
                    $hasDatabase = $hasDatabase || str_contains("/{$normalized}", '/db-dumps/');
                    $hasFiles = $hasFiles || str_starts_with($normalized, 'storage/app/');
                }

                $mode = Cast::mustString($manifest['mode'] ?? '');

                if (in_array($mode, ['all', 'database'], true) && ! $hasDatabase) {
                    throw new RuntimeException("Backup [{$backupId}] contains no database dump.");
                }

                if (in_array($mode, ['all', 'files'], true) && ! $hasFiles) {
                    throw new RuntimeException("Backup [{$backupId}] contains no storage/app content.");
                }
            } finally {
                $archive->close();
            }

            return [
                'status' => 'OK',
                'backup_id' => $backupId,
                'disk' => $diskName,
                'path' => $path,
                'size_bytes' => $disk->size($path),
                'sha256' => $checksum,
                'manifest' => $manifest,
            ];
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** @return array<string, mixed> */
    public function cleanup(): array
    {
        try {
            if (Artisan::call('backup:clean', ['--disable-notifications' => true, '--no-interaction' => true]) !== 0) {
                throw new RuntimeException(Str::limit(trim(Artisan::output()), 1000));
            }

            $deletedManifests = 0;

            foreach ($this->disks() as $diskName) {
                $disk = Storage::disk($diskName);

                foreach ($disk->allFiles($this->backupName()) as $path) {
                    if (! str_ends_with($path, '.zip.accelerator.json')) {
                        continue;
                    }

                    $archivePath = Str::beforeLast($path, '.accelerator.json');

                    if (! $disk->exists($archivePath) && $disk->delete($path)) {
                        $deletedManifests++;
                    }
                }
            }

            $this->notifier->send('backup cleanup', 'success', ['deleted_orphan_manifests' => $deletedManifests]);

            return [
                'status' => 'OK',
                'deleted_orphan_manifests' => $deletedManifests,
            ];
        } catch (Throwable $exception) {
            $this->notifier->send('backup cleanup', 'failed', ['error' => Str::limit($exception->getMessage(), 800)]);

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function manifest(string $backupId, string $path, string $diskName, string $mode, ?string $requestedRevision): array
    {
        $disk = Storage::disk($diskName);
        $revisionPath = base_path('REVISION');
        $revisionContents = is_file($revisionPath) ? file_get_contents($revisionPath) : false;
        $revision = $requestedRevision ?? (is_string($revisionContents) ? trim($revisionContents) : '');
        $connection = Cast::mustString(config('database.default'));
        $realBasePath = realpath(base_path());

        return [
            'schema' => 1,
            'backup_id' => $backupId,
            'deployment_key' => Cast::mustString(config('accelerator.operations.deployment_key')),
            'stage' => Cast::mustString(config('accelerator.operations.stage')),
            'domain' => Cast::mustString(config('accelerator.operations.domain')),
            'deploy_root' => Cast::mustString(config('accelerator.operations.deploy_root')),
            'created_at' => now('UTC')->toIso8601String(),
            'timezone' => Cast::mustString(config('app.timezone')),
            'revision' => $revision,
            'release' => is_string($realBasePath) ? basename($realBasePath) : null,
            'application' => Cast::mustString(config('app.name')),
            'environment' => Cast::mustString(config('app.env')),
            'database' => [
                'driver' => Cast::mustString(config("database.connections.{$connection}.driver", $connection)),
                'connection' => $connection,
                'database' => Cast::mustString(config("database.connections.{$connection}.database", '')),
            ],
            'mode' => $mode,
            'include' => array_values(array_filter(Arr::wrap(config('accelerator.backup.include', [storage_path('app')])), is_string(...))),
            'disk' => $diskName,
            'path' => $path,
            'size_bytes' => $disk->size($path),
            'sha256' => $this->checksum($disk, $path),
            'encrypted' => filled(config('backup.backup.password')),
            'versions' => [
                'accelerator' => InstalledVersions::getPrettyVersion('wireninja/accelerator') ?? 'source',
                'laravel_backup' => InstalledVersions::getPrettyVersion('spatie/laravel-backup') ?? 'unknown',
            ],
        ];
    }

    /** @param array<string, mixed> $manifest */
    private function writeManifest(string $diskName, string $archivePath, array $manifest): void
    {
        try {
            $contents = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the backup manifest.', previous: $exception);
        }

        if (! Storage::disk($diskName)->put($archivePath.'.accelerator.json', $contents)) {
            throw new RuntimeException("Unable to write backup manifest on disk [{$diskName}].");
        }
    }

    private function protectArtifact(string $diskName, string $archivePath): void
    {
        if ($diskName === config('accelerator.backup.s3.disk')) {
            return;
        }

        $disk = Storage::disk($diskName);

        foreach ([$archivePath, $archivePath.'.accelerator.json'] as $path) {
            try {
                $disk->setVisibility($path, 'private');
            } catch (Throwable $exception) {
                throw new RuntimeException("Unable to make backup artifact [{$path}] private on disk [{$diskName}].", previous: $exception);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function readManifest(Filesystem $disk, string $archivePath): ?array
    {
        $path = $archivePath.'.accelerator.json';

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $contents = $disk->get($path);

            if (! is_string($contents)) {
                throw new RuntimeException("Backup manifest [{$path}] could not be read.");
            }

            $manifest = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Backup manifest [{$path}] is invalid JSON.", previous: $exception);
        }

        return is_array($manifest) ? Cast::stringKeyedArray($manifest) : null;
    }

    private function findPath(string $diskName, string $filename): string
    {
        $disk = Storage::disk($diskName);

        foreach ($disk->allFiles($this->backupName()) as $path) {
            if (basename($path) === $filename) {
                return $path;
            }
        }

        throw new RuntimeException("Backup file [{$filename}] was not found on disk [{$diskName}].");
    }

    private function checksum(Filesystem $disk, string $path): string
    {
        $stream = $disk->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read backup archive [{$path}].");
        }

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }

    /** @return list<string> */
    private function disks(): array
    {
        $disks = array_values(array_filter(Arr::wrap(config('accelerator.backup.disks', ['local'])), is_string(...)));

        if ($disks === []) {
            throw new RuntimeException('At least one Accelerator backup disk is required.');
        }

        return $disks;
    }

    private function backupName(): string
    {
        $name = trim(Cast::mustString(config('accelerator.backup.name')));

        if ($name === '' || preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $name) !== 1) {
            throw new RuntimeException('ACCELERATOR_BACKUP_NAME must contain only letters, numbers, dots, underscores, and dashes.');
        }

        return $name;
    }

    private function assertEnabled(): void
    {
        if (! config('accelerator.backup.enabled', true)) {
            throw new RuntimeException('Accelerator backup is disabled for this environment.');
        }
    }

    private function assertBackupId(string $backupId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $backupId) !== 1) {
            throw new RuntimeException('Backup ID contains unsafe characters.');
        }
    }

    private function recordLifecycleState(string $result, ?string $backupId, ?string $error): void
    {
        $path = storage_path('framework/accelerator-backup-state.json');
        File::ensureDirectoryExists(dirname($path), 0700);
        $state = $this->lifecycleState() ?? [];
        $state[$result === 'success' ? 'last_success' : 'last_failure'] = [
            'backup_id' => $backupId,
            'occurred_at' => now('UTC')->toIso8601String(),
            'error' => $error,
        ];
        File::put($path, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL, true);
        File::chmod($path, 0600);
    }

    /** @return array<string, mixed>|null */
    private function lifecycleState(): ?array
    {
        $path = storage_path('framework/accelerator-backup-state.json');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return null;
        }

        $state = json_decode($contents, true);

        return is_array($state) ? Cast::stringKeyedArray($state) : null;
    }
}
