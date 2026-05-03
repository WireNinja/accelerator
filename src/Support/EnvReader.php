<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EnvReader
{
    protected static array $sensitiveKeywords = [
        'key', 'secret', 'password', 'token', 'auth', 'pass', 'crypt', 'salt', 'vapid', 'private', 'access',
    ];

    public static function redacted(array $specificKeys = []): array
    {
        $allEnv = self::readFromEnvFile();
        $data = [];

        $keysToCheck = ! empty($specificKeys) ? $specificKeys : array_keys($allEnv);

        foreach ($keysToCheck as $key) {
            $value = $allEnv[$key] ?? null;

            if ($value === null) {
                if (! empty($specificKeys)) {
                    $data[$key] = '[MISSING]';
                }

                continue;
            }

            $keyLower = strtolower($key);
            $isSensitive = Str::contains($keyLower, self::$sensitiveKeywords);

            // Special case: common IDs and public keys are usually not secrets
            if ($isSensitive && (Str::contains($keyLower, 'id') || Str::contains($keyLower, 'public')) && ! Str::contains($keyLower, ['secret', 'private'])) {
                $isSensitive = false;
            }

            if ($isSensitive && ! empty($value)) {
                $data[$key] = '[REDACTED]';
            } else {
                $data[$key] = empty($value) ? '[EMPTY]' : (is_string($value) ? trim($value, " \t\n\r\0\x0B\"'") : $value);
            }
        }

        // Sort by key for better readability
        ksort($data);

        return $data;
    }

    protected static function readFromEnvFile(): array
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            return [];
        }

        $lines = explode("\n", File::get($path));
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $data;
    }
}
