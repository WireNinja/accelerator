<?php

namespace WireNinja\Accelerator\Console\Ops;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('ops:backup-status')]
#[Description('Audit database backups and storage usage')]
class BackupStatusCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('📊 Fetching Spatie Backup List...');
        $this->call('backup:list');

        $appName = config('app.name');
        $backupDisk = config('backup.backup.destination.disks')[0] ?? 'local';

        // Cek path fisik berdasarkan disk
        try {
            $disk = Storage::disk($backupDisk);
            $files = $disk->allFiles($appName);
            $zipFiles = array_filter($files, fn (string $f): bool => str_ends_with($f, '.zip'));

            $this->newLine();
            $this->info(sprintf('📂 Physical Storage Audit (Disk: %s, App: %s)', $backupDisk, $appName));
            $this->line('---------------------------------------------------------');

            if ($zipFiles === []) {
                $this->warn('⚠️  No backup files found in: '.$disk->path($appName));

                return;
            }

            $totalSize = 0;
            $lastTime = 0;
            $lastFile = '';

            foreach ($zipFiles as $file) {
                $size = $disk->size($file);
                $time = $disk->lastModified($file);
                $totalSize += $size;

                if ($time > $lastTime) {
                    $lastTime = $time;
                    $lastFile = $file;
                }
            }

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Backup Files', count($zipFiles).' files'],
                    ['Total Storage Used', $this->formatBytes($totalSize)],
                    ['Last Backup Date', date('Y-m-d H:i:s', $lastTime)],
                    ['Last File Name', basename($lastFile)],
                ]
            );

        } catch (Exception $exception) {
            $this->error('❌ Error auditing storage: '.$exception->getMessage());
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int|float $bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
