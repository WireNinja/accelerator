<?php

namespace WireNinja\Accelerator\Console\Vps;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('vps:backup-status {--json : Output as JSON} {--compact : Compact JSON output}')]
#[Description('Audit database backups and storage usage')]
class BackupStatusCommand extends Command
{
    public function handle(): int
    {
        $appName = (string) config('app.name');
        $backupDisk = (string) (config('backup.backup.destination.disks')[0] ?? 'local');
        $isJson = (bool) $this->option('json');

        if (! $isJson) {
            $this->info('📊 Fetching Spatie Backup List...');
            // human mode pakai output verbose dari spatie sebagai konteks tambahan.
            $this->call('backup:list');
        }

        try {
            $disk = Storage::disk($backupDisk);
            // Spatie backup default tidak nested -> top-level files() cukup, hindari
            // allFiles() yang traversal full tree (mahal kalau banyak retensi).
            $files = $disk->files($appName);
            $zipFiles = array_values(array_filter($files, static fn(string $f): bool => str_ends_with($f, '.zip')));

            $totalSize = 0;
            $lastTime = 0;
            $lastFile = '';
            $details = [];

            foreach ($zipFiles as $file) {
                $size = $disk->size($file);
                $time = $disk->lastModified($file);

                $totalSize += $size;

                if ($time > $lastTime) {
                    $lastTime = $time;
                    $lastFile = $file;
                }

                if ($isJson) {
                    $details[] = [
                        'path' => $file,
                        'size_bytes' => $size,
                        'size_human' => $this->formatBytes($size),
                        'mtime' => date('c', $time),
                    ];
                }
            }

            if ($isJson) {
                $payload = [
                    'status' => $zipFiles === [] ? 'WARNING' : 'OK',
                    'disk' => $backupDisk,
                    'app_name' => $appName,
                    'physical_path' => $disk->path($appName),
                    'summary' => [
                        'total_files' => count($zipFiles),
                        'total_size_bytes' => $totalSize,
                        'total_size_human' => $this->formatBytes($totalSize),
                        'last_backup_at' => $lastTime > 0 ? date('c', $lastTime) : null,
                        'last_file' => $lastFile !== '' ? basename($lastFile) : null,
                    ],
                    'files' => $details,
                ];

                $flags = ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
                $this->output->writeln(json_encode($payload, $flags));

                return $zipFiles === [] ? 1 : 0;
            }

            $this->newLine();
            $this->info(sprintf('📂 Physical Storage Audit (Disk: %s, App: %s)', $backupDisk, $appName));
            $this->line('---------------------------------------------------------');

            if ($zipFiles === []) {
                $this->warn('⚠️  No backup files found in: ' . $disk->path($appName));

                return 1;
            }

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Backup Files', count($zipFiles) . ' files'],
                    ['Total Storage Used', $this->formatBytes($totalSize)],
                    ['Last Backup Date', date('Y-m-d H:i:s', $lastTime)],
                    ['Last File Name', basename($lastFile)],
                ]
            );

            return 0;
        } catch (Exception $exception) {
            if ($isJson) {
                $payload = [
                    'status' => 'ERROR',
                    'disk' => $backupDisk,
                    'app_name' => $appName,
                    'error' => $exception->getMessage(),
                ];

                $flags = ($this->option('compact') ? 0 : JSON_PRETTY_PRINT) | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
                $this->output->writeln(json_encode($payload, $flags));

                return 1;
            }

            $this->error('❌ Error auditing storage: ' . $exception->getMessage());

            return 1;
        }
    }

    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = (int) min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
