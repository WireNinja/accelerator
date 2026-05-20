<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

/**
 * Strips sensitive parameters and headers from request data before persistence.
 *
 * Configurable via `accelerator.telemetry.sensitive_params` and
 * `accelerator.telemetry.sensitive_headers`.
 */
final class SensitiveDataFilter
{
    /**
     * Filter sensitive values from a parameter array (body, query).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function filterParams(array $params): array
    {
        $sensitiveKeys = array_map(
            'strtolower',
            config('accelerator.telemetry.sensitive_params', [])
        );

        return self::redactKeys($params, $sensitiveKeys);
    }

    /**
     * Filter sensitive values from request headers.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function filterHeaders(array $headers): array
    {
        $sensitiveKeys = array_map(
            'strtolower',
            config('accelerator.telemetry.sensitive_headers', [])
        );

        return self::redactKeys($headers, $sensitiveKeys);
    }

    /**
     * Recursively redact keys that match the sensitive list.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $sensitiveKeys
     * @return array<string, mixed>
     */
    private static function redactKeys(array $data, array $sensitiveKeys): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = self::redactKeys($value, $sensitiveKeys);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
