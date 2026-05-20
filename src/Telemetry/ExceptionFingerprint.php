<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Throwable;

/**
 * Generates a stable fingerprint for exception grouping.
 *
 * Fingerprint is based on exception class + file + line, so the same exception
 * thrown from the same location always groups together regardless of message
 * variations (e.g. different IDs in "User [123] not found").
 */
final class ExceptionFingerprint
{
    /**
     * Generate a fingerprint hash for the given exception.
     */
    public static function generate(Throwable $exception): string
    {
        return md5(
            $exception::class.'|'.$exception->getFile().'|'.$exception->getLine()
        );
    }

    /**
     * Extract the grouping metadata from an exception.
     *
     * @return array{fingerprint: string, class: string, file: string, line: int}
     */
    public static function extract(Throwable $exception): array
    {
        return [
            'fingerprint' => self::generate($exception),
            'class' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
