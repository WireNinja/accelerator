<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Diagnostics;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Octane\Facades\Octane;
use Throwable;
use WireNinja\Accelerator\Support\Cast;
use WireNinja\Accelerator\Support\UserModel;

final class InsiderDiagnostics
{
    /** @return array<string, array<string, bool|int|string>> */
    public function collect(Request $request): array
    {
        $user = UserModel::current();
        $opcache = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $opcache = is_array($opcache) ? $opcache : [];
        $memory = is_array($opcache['memory_usage'] ?? null) ? $opcache['memory_usage'] : [];
        $statistics = is_array($opcache['opcache_statistics'] ?? null) ? $opcache['opcache_statistics'] : [];

        return [
            'Application' => [
                'Name' => (string) config('app.name'),
                'Environment' => (string) config('app.env'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'SAPI' => PHP_SAPI,
                'Octane request' => isset($_SERVER['LARAVEL_OCTANE']),
                'Memory' => $this->formatBytes(memory_get_usage(true)),
                'Peak memory' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'Identity' => [
                'User ID' => Cast::asString($user->getAuthIdentifier()),
                'Name' => Cast::asString($user->getAttribute('name')),
                'Roles' => $user->getRoleNames()->implode(', ') ?: '-',
                'Super Admin' => $user->isSuperAdmin(),
                'Verified' => $user->hasVerifiedEmail(),
                'Suspended' => $user->isSuspended(),
            ],
            'Session' => [
                'Driver' => (string) config('session.driver'),
                'Lifetime minutes' => (int) config('session.lifetime'),
                'Encrypted' => (bool) config('session.encrypt'),
                'Payload bytes' => strlen(serialize($request->session()->all())),
                'Key count' => count($request->session()->all()),
                ...$this->sessionTable(),
            ],
            'OPcache' => [
                'Loaded' => extension_loaded('Zend OPcache'),
                'Enabled' => (bool) ($opcache['opcache_enabled'] ?? false),
                'Cache full' => (bool) ($opcache['cache_full'] ?? false),
                'Restart pending' => (bool) ($opcache['restart_pending'] ?? false),
                'Used memory' => $this->formatBytes((int) ($memory['used_memory'] ?? 0)),
                'Free memory' => $this->formatBytes((int) ($memory['free_memory'] ?? 0)),
                'Wasted memory' => $this->formatBytes((int) ($memory['wasted_memory'] ?? 0)),
                'Cached scripts' => (int) ($statistics['num_cached_scripts'] ?? 0),
                'Hit rate' => round((float) ($statistics['opcache_hit_rate'] ?? 0), 2).'%',
            ],
            'Extensions' => [
                'redis' => extension_loaded('redis'),
                'swoole' => extension_loaded('swoole'),
                'imagick' => extension_loaded('imagick'),
            ],
        ];
    }

    /** @return array<string, bool|int|string> */
    private function sessionTable(): array
    {
        $tableName = (string) config('session.octane_table', 'sessions');
        $configured = Collection::make((array) config('octane.tables', []))
            ->mapWithKeys(static fn (array $columns, string $name): array => [explode(':', $name)[0] => $columns])
            ->has($tableName);

        try {
            $table = Octane::table($tableName);

            return [
                'Octane table configured' => $configured,
                'Octane active rows' => $table->count(),
                'Octane table memory' => $this->formatBytes((int) $table->getMemorySize()),
            ];
        } catch (Throwable) {
            return [
                'Octane table configured' => $configured,
                'Octane active rows' => 'unavailable',
                'Octane table memory' => 'unavailable',
            ];
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return round($value, 2).' '.$unit;
            }

            $value /= 1024;
        }

        return $bytes.' B';
    }
}
