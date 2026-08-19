<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Backup;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final readonly class BackupArchive
{
    public function assertSafe(ZipArchive $archive, string $backupId): void
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entry = $archive->getNameIndex($index);

            if (! is_string($entry) || $this->unsafeEntry($entry) || $this->entryIsSymlink($archive, $index)) {
                throw new RuntimeException("Backup [{$backupId}] contains an unsafe archive entry.");
            }
        }
    }

    public function findDatabaseDump(string $extractedPath): ?string
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractedPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isFile() && str_contains(str_replace('\\', '/', $file->getPathname()), '/db-dumps/')) {
                return $file->getPathname();
            }
        }

        return null;
    }

    private function unsafeEntry(string $entry): bool
    {
        $normalized = str_replace('\\', '/', $entry);

        return str_starts_with($normalized, '/')
            || preg_match('/^[a-z]:\//i', $normalized) === 1
            || in_array('..', explode('/', $normalized), true);
    }

    private function entryIsSymlink(ZipArchive $archive, int $index): bool
    {
        if (! $archive->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
            return false;
        }

        return is_int($operatingSystem)
            && is_int($attributes)
            && $operatingSystem === ZipArchive::OPSYS_UNIX
            && (($attributes >> 16) & 0170000) === 0120000;
    }
}
