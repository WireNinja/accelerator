<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

final class SensitiveDataFilter
{
    private const REDACTED = '[REDACTED]';

    private const MAX_DEPTH = 5;

    private const MAX_ITEMS = 50;

    private const MAX_STRING_LENGTH = 1000;

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public static function parameters(array $parameters): array
    {
        return self::redactArray($parameters, self::sensitiveParameters());
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function headers(array $headers): array
    {
        return self::redactArray($headers, self::sensitiveHeaders());
    }

    public static function text(string $value, int $maximumLength = 4000): string
    {
        $tokens = array_map(static fn (string $token): string => preg_quote($token, '/'), self::sensitiveParameters());
        $filtered = $value;

        if ($tokens !== []) {
            $pattern = '/((?:'.implode('|', $tokens).')\s*[=:]\s*)([^\s&,;]+)/i';
            $filtered = preg_replace($pattern, '$1'.self::REDACTED, $filtered) ?? $filtered;
        }

        $filtered = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer '.self::REDACTED, $filtered) ?? $filtered;

        return mb_substr($filtered, 0, max(1, $maximumLength));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $sensitiveTokens
     * @return array<string, mixed>
     */
    private static function redactArray(array $values, array $sensitiveTokens, int $depth = 0): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['__truncated' => true];
        }

        $filtered = [];
        $processed = 0;

        foreach ($values as $key => $value) {
            if ($processed >= self::MAX_ITEMS) {
                $filtered['__truncated'] = true;

                break;
            }

            $processed++;
            $normalizedKey = strtolower((string) $key);

            if (self::containsToken($normalizedKey, $sensitiveTokens)) {
                $filtered[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $filtered[$key] = self::redactArray($value, $sensitiveTokens, $depth + 1);
            } elseif (is_string($value)) {
                $filtered[$key] = self::text($value, self::MAX_STRING_LENGTH);
            } elseif (is_scalar($value) || $value === null) {
                $filtered[$key] = $value;
            } else {
                $filtered[$key] = '['.get_debug_type($value).']';
            }
        }

        return $filtered;
    }

    /**
     * @param  list<string>  $sensitiveTokens
     */
    private static function containsToken(string $key, array $sensitiveTokens): bool
    {
        foreach ($sensitiveTokens as $token) {
            if ($token !== '' && str_contains($key, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function sensitiveParameters(): array
    {
        return self::normalizedConfig('accelerator.telemetry.sensitive_params');
    }

    /**
     * @return list<string>
     */
    private static function sensitiveHeaders(): array
    {
        return self::normalizedConfig('accelerator.telemetry.sensitive_headers');
    }

    /**
     * @return list<string>
     */
    private static function normalizedConfig(string $key): array
    {
        $configured = config($key, []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? strtolower(trim($value)) : '',
            $configured,
        )));
    }
}
