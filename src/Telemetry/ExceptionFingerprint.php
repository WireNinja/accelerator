<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Http\Request;
use Throwable;

final class ExceptionFingerprint
{
    /**
     * @return array{fingerprint: string, class: class-string<Throwable>, file: string, line: int, route_name: string|null}
     */
    public static function describe(Throwable $exception, ?Request $request = null): array
    {
        [$file, $line] = self::applicationFrame($exception);
        $routeName = self::routeName($request);

        return [
            'fingerprint' => hash('sha256', implode('|', [
                $exception::class,
                $file,
                (string) $line,
                $routeName ?? '-',
            ])),
            'class' => $exception::class,
            'file' => $file,
            'line' => $line,
            'route_name' => $routeName,
        ];
    }

    /**
     * Prefer the first application frame when an exception originates in vendor code.
     *
     * @return array{string, int}
     */
    private static function applicationFrame(Throwable $exception): array
    {
        $candidate = self::relativeApplicationPath($exception->getFile());

        if ($candidate !== null) {
            return [$candidate, $exception->getLine()];
        }

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file)) {
                continue;
            }

            $candidate = self::relativeApplicationPath($file);

            if ($candidate !== null) {
                return [$candidate, (int) ($frame['line'] ?? 0)];
            }
        }

        return [self::relativePath($exception->getFile()), $exception->getLine()];
    }

    private static function relativeApplicationPath(string $file): ?string
    {
        $relative = self::relativePath($file);

        if ($relative === $file || str_starts_with($relative, 'vendor/')) {
            return null;
        }

        return $relative;
    }

    private static function relativePath(string $file): string
    {
        $normalizedFile = str_replace('\\', '/', $file);
        $normalizedBase = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        return str_starts_with($normalizedFile, $normalizedBase)
            ? substr($normalizedFile, strlen($normalizedBase))
            : $normalizedFile;
    }

    private static function routeName(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $name = $request->route()?->getName();

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $uri = $request->route()?->uri();

        return is_string($uri) && $uri !== '' ? $uri : null;
    }
}
