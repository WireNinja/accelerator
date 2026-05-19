<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Support\Facades\File;

class EnvReader
{
    /**
     * Token-based sensitive matching.
     *
     * Setiap key env di-split via `_` lalu di-cek per token. Hasilnya lebih ketat
     * dari substring match — `WIDGET_KEY` match `key`, tapi `KEYCHAIN_HINT` tidak.
     */
    protected static array $sensitiveTokens = [
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
        'webhook',
        'signature',
        'cipher',
        'bearer',
        'cred',
        'credential',
        'dsn',
    ];

    /**
     * Token yang men-downgrade key dari sensitive ke non-sensitive.
     * Contoh: `GOOGLE_CLIENT_ID` punya token `id` -> dianggap public ID.
     */
    protected static array $publicTokens = [
        'id',
        'public',
    ];

    /**
     * Token yang membatalkan downgrade public di atas. Kalau key tetap mengandung
     * token ini, `public/id` whitelist tidak boleh aktif.
     */
    protected static array $hardSensitiveTokens = [
        'secret',
        'private',
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

            $isSensitive = self::isSensitiveKey($key);

            if ($isSensitive && $value !== '') {
                $data[$key] = '[REDACTED]';

                continue;
            }

            // Sengaja pakai $value === '' (BUKAN empty()) supaya literal "0" tidak
            // di-treat sebagai empty.
            if ($value === '') {
                $data[$key] = '[EMPTY]';

                continue;
            }

            $data[$key] = is_string($value) ? trim($value, " \t\n\r\0\x0B\"'") : $value;
        }

        ksort($data);

        return $data;
    }

    protected static function isSensitiveKey(string $key): bool
    {
        $tokens = self::tokenize($key);

        $matchedSensitive = array_intersect($tokens, self::$sensitiveTokens) !== [];

        if (! $matchedSensitive) {
            return false;
        }

        $hasHardSensitive = array_intersect($tokens, self::$hardSensitiveTokens) !== [];

        if ($hasHardSensitive) {
            return true;
        }

        $hasPublicMarker = array_intersect($tokens, self::$publicTokens) !== [];

        return ! $hasPublicMarker;
    }

    /**
     * @return array<int, string>
     */
    protected static function tokenize(string $key): array
    {
        return array_values(array_filter(explode('_', strtolower($key)), fn (string $token): bool => $token !== ''));
    }

    protected static function readFromEnvFile(): array
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', File::get($path)) ?: [];
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }

        return $data;
    }
}
