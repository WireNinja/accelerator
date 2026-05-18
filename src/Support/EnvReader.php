<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EnvReader
{
    // TODO(deep-analysis): keyword list belum cover `webhook`, `signature`, `cipher`, `bearer`,
    // `cred`, `dsn`. Pertimbangkan ekspansi setelah mapping kasus nyata project lain.
    protected static array $sensitiveKeywords = [
        'key',
        'secret',
        'password',
        'token',
        'auth',
        'pass',
        'crypt',
        'salt',
        'vapid',
        'private',
        'access',
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
            // TODO(deep-analysis): substring match terlalu liberal. `OPENID_TOKEN` ke-detect via
            // substring `token` (oke), tapi `WIDGET_KEY` juga match `key` walaupun bukan secret.
            // Ganti ke token-based split (explode '_' lalu in_array) di Phase 3.
            $isSensitive = Str::contains($keyLower, self::$sensitiveKeywords);

            // Special case: common IDs and public keys are usually not secrets.
            // TODO(deep-analysis): `Str::contains($keyLower, 'id')` bisa false-negative.
            // `OPENID_TOKEN` punya substring `id` -> akan un-mark sensitive padahal token sensitive.
            // Token-based check di Phase 3.
            if ($isSensitive && (Str::contains($keyLower, 'id') || Str::contains($keyLower, 'public')) && ! Str::contains($keyLower, ['secret', 'private'])) {
                $isSensitive = false;
            }

            if ($isSensitive && ! empty($value)) {
                $data[$key] = '[REDACTED]';
            } else {
                // TODO(deep-analysis): `empty('0')` === true di PHP, jadi env literal `0` di-treat
                // sebagai `[EMPTY]`. Ganti ke `$value === ''` cek di Phase 3.
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
