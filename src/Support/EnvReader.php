<?php

namespace WireNinja\Accelerator\Support;

use Illuminate\Support\Facades\File;

class EnvReader
{
    /**
     * Token-based sensitive matching.
     *
     * Each env key is split by `_` then checked per token. More precise than substring
     * matching — `WIDGET_KEY` matches `key`, but `KEYCHAIN_HINT` does not.
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
     * Tokens that downgrade a key from sensitive to non-sensitive.
     * Example: `GOOGLE_CLIENT_ID` has token `id` -> treated as public identifier.
     */
    protected static array $publicTokens = [
        'id',
        'public',
    ];

    /**
     * Tokens that cancel the public downgrade above. If the key also contains one
     * of these tokens, the `public/id` whitelist must not activate.
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

            // Intentionally use $value === '' (NOT empty()) so literal "0" is not
            // treated as empty.
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
