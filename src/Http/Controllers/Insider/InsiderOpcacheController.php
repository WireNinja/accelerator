<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Insider;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InsiderOpcacheController extends Controller
{
    public function debugOpcache(): string
    {
        $this->authorizeAccess();

        $opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(true) : false;
        $opcacheConfig = function_exists('opcache_get_configuration') ? @opcache_get_configuration() : false;

        $content = [];
        $content[] = '<h1>Insider OPCache Debug</h1>';

        if ($opcacheStatus === false) {
            $content[] = '<p><b>OPCache is not enabled or not available.</b></p>';
        } else {
            // Memory & statistics
            $memory = $opcacheStatus['memory_usage'] ?? [];
            $stats = $opcacheStatus['opcache_statistics'] ?? [];
            $directives = $opcacheConfig['directives'] ?? [];

            $content[] = $this->renderTable('OPCache Memory & Stats', [
                ['OPCache Enabled', ($opcacheStatus['opcache_enabled'] ?? false) ? 'YES' : 'NO'],
                ['Cache Full', ($opcacheStatus['cache_full'] ?? false) ? 'YES' : 'NO'],
                ['Restart Pending', ($opcacheStatus['restart_pending'] ?? false) ? 'YES' : 'NO'],
                ['Restart In Progress', ($opcacheStatus['restart_in_progress'] ?? false) ? 'YES' : 'NO'],
                ['Used Memory', $this->formatBytes((int) ($memory['used_memory'] ?? 0))],
                ['Free Memory', $this->formatBytes((int) ($memory['free_memory'] ?? 0))],
                ['Wasted Memory', $this->formatBytes((int) ($memory['wasted_memory'] ?? 0))],
                ['Cached Scripts Count', (string) ($stats['num_cached_scripts'] ?? 0)],
                ['Hits', (string) ($stats['hits'] ?? 0)],
                ['Misses', (string) ($stats['misses'] ?? 0)],
                ['Hit Rate', round((float) ($stats['opcache_hit_rate'] ?? 0), 2).' %'],
            ]);

            // Configuration directives
            $directiveRows = [];
            foreach ($directives as $key => $value) {
                if (is_bool($value)) {
                    $valStr = $value ? 'true' : 'false';
                } else {
                    $valStr = (string) $value;
                }
                $directiveRows[] = [$key, $valStr];
            }
            $content[] = $this->renderTable('OPCache Directives (Configuration)', $directiveRows);

            // Cached scripts
            $scripts = $opcacheStatus['scripts'] ?? [];
            $scriptRows = [];
            if (! empty($scripts)) {
                foreach ($scripts as $path => $info) {
                    $scriptRows[] = [
                        basename($path),
                        sprintf(
                            'Hits: %d | Memory: %s | Path: <small>%s</small>',
                            $info['hits'] ?? 0,
                            $this->formatBytes((int) ($info['memory_consumption'] ?? 0)),
                            e($path)
                        ),
                    ];
                }
                $content[] = $this->renderTable('Cached Scripts (Sample)', array_slice($scriptRows, 0, 100));
                if (count($scriptRows) > 100) {
                    $content[] = '<p><i>Showing first 100 of '.count($scriptRows).' cached scripts.</i></p>';
                }
            } else {
                $content[] = '<h2>Cached Scripts</h2><p>No scripts currently cached.</p>';
            }
        }

        return $this->renderPage('Insider OPCache Debug', implode('', $content));
    }

    public function stats(Request $request): string
    {
        $this->authorizeAccess();

        $opcacheStatus = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
        $isOpcacheStatusAvailable = is_array($opcacheStatus);
        $jitRuntime = $isOpcacheStatusAvailable && isset($opcacheStatus['jit']) && is_array($opcacheStatus['jit'])
            ? $opcacheStatus['jit']
            : null;

        $content = [];
        $content[] = '<h1>Insider Diagnostics Stats</h1>';

        $content[] = $this->renderTable('System Runtime', [
            ['Timestamp', now()->toIso8601String()],
            ['PHP Version', PHP_VERSION],
            ['PHP SAPI', PHP_SAPI],
            ['App Env', config('app.env')],
            ['PID', (string) getmypid()],
            ['Memory Usage', $this->formatBytes((int) memory_get_usage(true))],
            ['Memory Peak Usage', $this->formatBytes((int) memory_get_peak_usage(true))],
        ]);

        $content[] = $this->renderTable('OPCache Runtime', [
            ['Loaded', extension_loaded('Zend OPcache') ? 'YES' : 'NO'],
            ['opcache.enable (INI)', ini_get('opcache.enable') === '1' ? 'YES' : 'NO'],
            ['opcache.enable_cli (INI)', ini_get('opcache.enable_cli') === '1' ? 'YES' : 'NO'],
            ['Status Available', $isOpcacheStatusAvailable ? 'YES' : 'NO'],
            ['Enabled Runtime', $isOpcacheStatusAvailable && ($opcacheStatus['opcache_enabled'] ?? false) ? 'YES' : 'NO'],
            ['Cache Full', $isOpcacheStatusAvailable && ($opcacheStatus['cache_full'] ?? false) ? 'YES' : 'NO'],
            ['Restart Pending', $isOpcacheStatusAvailable && ($opcacheStatus['restart_pending'] ?? false) ? 'YES' : 'NO'],
            ['Restart In Progress', $isOpcacheStatusAvailable && ($opcacheStatus['restart_in_progress'] ?? false) ? 'YES' : 'NO'],
            ['Used Memory', $isOpcacheStatusAvailable ? $this->formatBytes((int) ($opcacheStatus['memory_usage']['used_memory'] ?? 0)) : '-'],
            ['Free Memory', $isOpcacheStatusAvailable ? $this->formatBytes((int) ($opcacheStatus['memory_usage']['free_memory'] ?? 0)) : '-'],
            ['Wasted Memory', $isOpcacheStatusAvailable ? $this->formatBytes((int) ($opcacheStatus['memory_usage']['wasted_memory'] ?? 0)) : '-'],
            ['Cached Scripts', $isOpcacheStatusAvailable ? (string) ($opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? 0) : '-'],
            ['Max Cached Keys', $isOpcacheStatusAvailable ? (string) ($opcacheStatus['opcache_statistics']['max_cached_keys'] ?? 0) : '-'],
            ['Hits', $isOpcacheStatusAvailable ? (string) ($opcacheStatus['opcache_statistics']['hits'] ?? 0) : '-'],
            ['Misses', $isOpcacheStatusAvailable ? (string) ($opcacheStatus['opcache_statistics']['misses'] ?? 0) : '-'],
        ]);

        $content[] = $this->renderTable('JIT Runtime', [
            ['INI Mode', (string) ini_get('opcache.jit')],
            ['INI Buffer Size', (string) ini_get('opcache.jit_buffer_size')],
            ['Enabled Runtime', $jitRuntime !== null && ($jitRuntime['enabled'] ?? false) ? 'YES' : 'NO'],
            ['On Runtime', $jitRuntime !== null && ($jitRuntime['on'] ?? false) ? 'YES' : 'NO'],
            ['Kind', $jitRuntime['kind'] ?? '-'],
            ['Opt Level', (string) ($jitRuntime['opt_level'] ?? '-')],
            ['Opt Flags', (string) ($jitRuntime['opt_flags'] ?? '-')],
            ['Buffer Size', $jitRuntime !== null && isset($jitRuntime['buffer_size']) ? $this->formatBytes((int) $jitRuntime['buffer_size']) : '-'],
            ['Buffer Free', $jitRuntime !== null && isset($jitRuntime['buffer_free']) ? $this->formatBytes((int) $jitRuntime['buffer_free']) : '-'],
        ]);

        $content[] = $this->renderTable('Extensions', [
            ['imagick', extension_loaded('imagick') ? 'YES' : 'NO'],
            ['redis', extension_loaded('redis') ? 'YES' : 'NO'],
            ['swoole', extension_loaded('swoole') ? 'YES' : 'NO'],
        ]);

        return $this->renderPage('Insider Diagnostics Stats', implode('', $content));
    }

    private function authorizeAccess(): void
    {
        $user = mustUser();

        abort_unless($user->isSuperAdmin(), 403);
    }

    private function renderPage(string $title, string $content): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>{$title}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; padding: 20px; color: #333; background: #f8fafc; }
                h1, h2 { color: #0f172a; }
                table { width: 100%; max-width: 1200px; border-collapse: collapse; margin-bottom: 30px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
                th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                tr:hover { background-color: #f8fafc; }
                td:first-child { font-weight: 600; width: 300px; color: #475569; }
                a { color: #2563eb; text-decoration: none; }
                a:hover { text-decoration: underline; }
                center { text-align: left; max-width: 1200px; margin: 0 auto; }
            </style>
        </head>
        <body>
            <center>
                <h2>Navigation</h2>
                <table>
                    <tr>
                        <td><b>Links</b></td>
                        <td>
                            <a href="/insider">Dashboard</a> | 
                            <a href="/insider/sessions">Session & Identity</a> | 
                            <a href="/insider/debug-opcache">Debug OPCache</a> | 
                            <a href="/insider/stats">Diagnostics Stats</a>
                        </td>
                    </tr>
                </table>
                {$content}
            </center>
        </body>
        </html>
        HTML;
    }

    private function renderTable(string $title, array $rows): string
    {
        $body = '';

        foreach ($rows as [$label, $value]) {
            $body .= <<<HTML
            <tr>
                <td valign="top">{$label}</td>
                <td valign="top">{$value}</td>
            </tr>
            HTML;
        }

        return <<<HTML
        <h2>{$title}</h2>
        <table>
            {$body}
        </table>
        HTML;
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

        return (string) $bytes;
    }
}
