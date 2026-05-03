<?php

namespace WireNinja\Accelerator\Http\Controllers\Insider;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;
use Laravel\Octane\Facades\Octane;
use Throwable;
use WireNinja\Accelerator\Model\AcceleratedUser;

class InsiderDashboardController extends Controller
{
    public function index(Request $request): string
    {
        $user = $this->authorizeAccess();

        $sessionData = $request->session()->all();
        $opcacheStatus = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $opcacheConfig = function_exists('opcache_get_configuration') ? opcache_get_configuration() : false;
        $sessionTableStats = $this->sessionTableStats();

        $feedback = $this->renderFeedback();
        $content = [
            '<h1>Insider Runtime Dashboard</h1>',
            '<p><b>Internal only.</b> Endpoint ini hanya untuk super admin.</p>',
            $feedback,
            $this->renderTable('Identity & Access', [
                ['User ID', (string) $user->getKey()],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Roles', $user->getRoleNames()->implode(', ') ?: '-'],
                ['Super Admin', $user->isSuperAdmin() ? 'YES' : 'NO'],
                ['Verified Email', $user->hasVerifiedEmail() ? 'YES' : 'NO'],
                ['Suspended', $user->isSuspended() ? 'YES' : 'NO'],
                ['Online', $user->isOnline() ? 'YES' : 'NO'],
            ]),
            $this->renderTable('Request', [
                ['Method', $request->method()],
                ['Full URL', $request->fullUrl()],
                ['Path', $request->path()],
                ['Host', $request->getHost()],
                ['Scheme', $request->getScheme()],
                ['IP', $request->ip() ?? '-'],
                ['User Agent', (string) $request->userAgent()],
                ['Referer', (string) $request->headers->get('referer', '-')],
            ]),
            $this->renderTable('Application Runtime', [
                ['App Name', (string) config('app.name')],
                ['Laravel Version', app()->version()],
                ['App Env', (string) config('app.env')],
                ['App Debug', config('app.debug') ? 'true' : 'false'],
                ['App Timezone', (string) config('app.timezone')],
                ['App URL', (string) config('app.url')],
                ['Server Runtime', (string) config('accelerator.runtime', '-')],
                ['Octane Server', (string) config('octane.server', '-')],
                ['Running In Octane', isset($_SERVER['LARAVEL_OCTANE']) ? 'YES' : 'NO'],
                ['PHP Binary', PHP_BINARY],
                ['PHP Version', PHP_VERSION],
                ['PHP SAPI', PHP_SAPI],
                ['System', php_uname()],
            ]),
            $this->renderTable('Session', [
                ['Session Driver', (string) config('session.driver')],
                ['Session Cookie', (string) config('session.cookie')],
                ['Session ID', $request->session()->getId()],
                ['Session Lifetime (minutes)', (string) config('session.lifetime')],
                ['Session Encrypted', config('session.encrypt') ? 'true' : 'false'],
                ['Session Path', (string) config('session.path')],
                ['Session Domain', (string) (config('session.domain') ?: 'null')],
                ['Session Table Name', (string) config('session.octane_table', '-')],
                ['Session Payload Bytes', (string) strlen(serialize($sessionData))],
                ['Session Key Count', (string) count($sessionData)],
                ['Write Counter', (string) ((int) $request->session()->get('insider_session_counter', 0))],
                ['Session Note Bytes', (string) strlen((string) $request->session()->get('insider_session_note', ''))],
                ['Last Write At', (string) $request->session()->get('insider_last_write_at', '-')],
            ]),
            $this->renderTable('Octane Session Table', [
                ['Configured Name', (string) config('session.octane_table', '-')],
                ['Configured Rows', $sessionTableStats['configured_rows']],
                ['Configured Payload Bytes', $sessionTableStats['configured_payload_bytes']],
                ['Active Rows', $sessionTableStats['active_rows']],
                ['Memory Size', $sessionTableStats['memory_size']],
                ['Status', $sessionTableStats['status']],
            ]),
            $this->renderTable('Memory & GC', [
                ['Memory Limit', (string) ini_get('memory_limit')],
                ['Current Memory', $this->formatBytes(memory_get_usage())],
                ['Current Real Memory', $this->formatBytes(memory_get_usage(true))],
                ['Peak Memory', $this->formatBytes(memory_get_peak_usage())],
                ['Peak Real Memory', $this->formatBytes(memory_get_peak_usage(true))],
                ['GC Enabled', gc_enabled() ? 'YES' : 'NO'],
                ['GC Runs', (string) (($gcStatus = gc_status())['runs'] ?? 0)],
                ['GC Collected', (string) ($gcStatus['collected'] ?? 0)],
                ['GC Threshold', (string) ($gcStatus['threshold'] ?? 0)],
                ['Realpath Cache Size', $this->formatBytes((int) realpath_cache_size())],
                ['Realpath Cache Entries', (string) count(realpath_cache_get())],
                ['Max Execution Time', (string) ini_get('max_execution_time')],
            ]),
            $this->renderTable('OPCache', $this->opcacheRows($opcacheStatus, $opcacheConfig)),
            $this->renderTable('JIT', $this->jitRows($opcacheStatus)),
            $this->renderTable('Extensions', [
                ['swoole', extension_loaded('swoole') ? 'loaded '.(phpversion('swoole') ?: '') : 'not loaded'],
                ['openswoole', extension_loaded('openswoole') ? 'loaded '.(phpversion('openswoole') ?: '') : 'not loaded'],
                ['redis', extension_loaded('redis') ? 'loaded '.(phpversion('redis') ?: '') : 'not loaded'],
                ['pdo_sqlite', extension_loaded('pdo_sqlite') ? 'loaded' : 'not loaded'],
                ['opcache', extension_loaded('Zend OPcache') ? 'loaded '.(phpversion('Zend OPcache') ?: '') : 'not loaded'],
                ['intl', extension_loaded('intl') ? 'loaded' : 'not loaded'],
                ['mbstring', extension_loaded('mbstring') ? 'loaded' : 'not loaded'],
                ['Total Loaded Extensions', (string) count(get_loaded_extensions())],
            ]),
            $this->renderTable('Connections & Drivers', [
                ['Default DB Connection', (string) config('database.default')],
                ['Default Cache Store', (string) config('cache.default')],
                ['Default Queue Connection', (string) config('queue.default')],
                ['Broadcast Connection', (string) config('broadcasting.default')],
                ['Scout Driver', (string) config('scout.driver')],
            ]),
            $this->renderTable('Mutation Actions', [
                ['Update Session', $this->renderUpdateSessionForm($request)],
                ['Regenerate Session ID', $this->renderActionForm('/insider/regenerate', 'Regenerate Session ID', $request)],
                ['Logout', $this->renderActionForm('/logout', 'Logout', $request)],
            ]),
            '<h2>Full Session Dump</h2>',
            '<pre>'.e(var_export($sessionData, true)).'</pre>',
            '<h2>Loaded Extensions</h2>',
            '<pre>'.e(implode(', ', get_loaded_extensions())).'</pre>',
        ];

        return $this->renderPage('Insider Runtime Dashboard', implode('', $content));
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:60000'],
        ]);

        $request->session()->put(
            'insider_session_counter',
            ((int) $request->session()->get('insider_session_counter', 0)) + 1,
        );
        $request->session()->put('insider_session_note', $validated['note'] ?? '');
        $request->session()->put('insider_last_write_at', now()->format('Y-m-d H:i:s'));

        return redirect('/insider')->with('status', 'Session updated.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $request->session()->regenerate();

        return redirect('/insider')->with('status', 'Session ID regenerated.');
    }

    private function authorizeAccess(): AcceleratedUser
    {
        $user = mustUser();

        abort_unless($user->isSuperAdmin(), 403);

        return $user;
    }

    private function renderPage(string $title, string $content): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><title>{$title}</title></head>
        <body bgcolor="#ffffff" text="#000000">
            <center>
                {$content}
            </center>
        </body>
        </html>
        HTML;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function renderTable(string $title, array $rows): string
    {
        $body = '';

        foreach ($rows as [$label, $value]) {
            $body .= <<<HTML
            <tr>
                <td valign="top" align="right"><b>{$label}</b></td>
                <td valign="top" align="left">{$value}</td>
            </tr>
            HTML;
        }

        return <<<HTML
        <h2>{$title}</h2>
        <table border="1" cellpadding="6" cellspacing="0" width="1200">
            {$body}
        </table>
        <br>
        HTML;
    }

    private function renderFeedback(): string
    {
        $status = session('status');
        $error = session('error');
        $errors = session('errors');
        $content = '';

        if (is_string($status) && $status !== '') {
            $content .= '<p><b>'.e($status).'</b></p>';
        }

        if (is_string($error) && $error !== '') {
            $content .= '<p><b>'.e($error).'</b></p>';
        }

        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $items = '';

            foreach ($errors->all() as $message) {
                $items .= '<li>'.e($message).'</li>';
            }

            $content .= '<ul>'.$items.'</ul>';
        }

        return $content;
    }

    private function renderUpdateSessionForm(Request $request): string
    {
        $csrf = csrf_field();
        $note = e((string) $request->session()->get('insider_session_note', ''));

        return <<<HTML
        <form method="POST" action="/insider/session">
            {$csrf}
            <table border="1" cellpadding="4" cellspacing="0">
                <tr>
                    <td><textarea name="note" rows="8" cols="100">{$note}</textarea></td>
                </tr>
                <tr>
                    <td align="center"><input type="submit" value="Update Session"></td>
                </tr>
            </table>
        </form>
        HTML;
    }

    private function renderActionForm(string $action, string $label, Request $request): string
    {
        $csrf = csrf_field();

        return <<<HTML
        <form method="POST" action="{$action}">
            {$csrf}
            <input type="submit" value="{$label}">
        </form>
        HTML;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function opcacheRows(array|false $opcacheStatus, array|false $opcacheConfig): array
    {
        if ($opcacheStatus === false) {
            return [
                ['Enabled', 'NO'],
                ['Status', 'OPCache not available'],
            ];
        }

        $memoryUsage = $opcacheStatus['memory_usage'] ?? [];
        $statistics = $opcacheStatus['opcache_statistics'] ?? [];
        $directives = $opcacheConfig['directives'] ?? [];

        return [
            ['Enabled', 'YES'],
            ['Version', (string) (phpversion('Zend OPcache') ?: '-')],
            ['Used Memory', $this->formatBytes((int) ($memoryUsage['used_memory'] ?? 0))],
            ['Free Memory', $this->formatBytes((int) ($memoryUsage['free_memory'] ?? 0))],
            ['Wasted Memory', $this->formatBytes((int) ($memoryUsage['wasted_memory'] ?? 0))],
            ['Wasted Percentage', (string) round((float) ($memoryUsage['current_wasted_percentage'] ?? 0), 2).' %'],
            ['Cached Scripts', (string) ($statistics['num_cached_scripts'] ?? 0)],
            ['Cached Keys', (string) ($statistics['num_cached_keys'] ?? 0)],
            ['Max Cached Keys', (string) ($statistics['max_cached_keys'] ?? 0)],
            ['Hits', (string) ($statistics['hits'] ?? 0)],
            ['Misses', (string) ($statistics['misses'] ?? 0)],
            ['Blacklist Misses', (string) ($statistics['blacklist_misses'] ?? 0)],
            ['Hit Rate', (string) round((float) ($statistics['opcache_hit_rate'] ?? 0), 2).' %'],
            ['OOM Restarts', (string) ($statistics['oom_restarts'] ?? 0)],
            ['Hash Restarts', (string) ($statistics['hash_restarts'] ?? 0)],
            ['Manual Restarts', (string) ($statistics['manual_restarts'] ?? 0)],
            ['Restart Pending', ! empty($opcacheStatus['restart_pending']) ? 'YES' : 'NO'],
            ['Validate Timestamps', ! empty($directives['opcache.validate_timestamps']) ? 'YES' : 'NO'],
            ['Revalidate Frequency', (string) ($directives['opcache.revalidate_freq'] ?? '-')],
            ['Memory Consumption Limit', (string) ($directives['opcache.memory_consumption'] ?? '-')],
            ['Interned Strings Buffer', (string) ($directives['opcache.interned_strings_buffer'] ?? '-')],
            ['Max Accelerated Files', (string) ($directives['opcache.max_accelerated_files'] ?? '-')],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function jitRows(array|false $opcacheStatus): array
    {
        $jitValue = (string) ini_get('opcache.jit');
        $jitEnabled = $opcacheStatus !== false && $jitValue !== '' && $jitValue !== '0' && strtolower($jitValue) !== 'off';
        $jitStatus = is_array($opcacheStatus) ? ($opcacheStatus['jit'] ?? []) : [];

        return [
            ['Enabled', $jitEnabled ? 'YES' : 'NO'],
            ['Strategy', $jitValue === '' ? '-' : $jitValue],
            ['Buffer Size', isset($jitStatus['buffer_size']) ? $this->formatBytes((int) $jitStatus['buffer_size']) : '-'],
            ['Buffer Free', isset($jitStatus['buffer_free']) ? $this->formatBytes((int) $jitStatus['buffer_free']) : '-'],
            ['On', isset($jitStatus['on']) ? ((int) $jitStatus['on'] === 1 ? 'YES' : 'NO') : '-'],
            ['Kind', isset($jitStatus['kind']) ? (string) $jitStatus['kind'] : '-'],
            ['Opt Level', isset($jitStatus['opt_level']) ? (string) $jitStatus['opt_level'] : '-'],
            ['Opt Flags', isset($jitStatus['opt_flags']) ? (string) $jitStatus['opt_flags'] : '-'],
        ];
    }

    /**
     * @return array{configured_rows: string, configured_payload_bytes: string, active_rows: string, memory_size: string, status: string}
     */
    private function sessionTableStats(): array
    {
        $tableName = (string) config('session.octane_table', 'sessions');
        $configuredTable = Collection::make((array) config('octane.tables', []))
            ->mapWithKeys(fn (array $columns, string $name): array => [explode(':', $name)[0] => ['key' => $name, 'columns' => $columns]])
            ->get($tableName);

        $configuredRows = '-';
        $configuredPayloadBytes = '-';

        if (is_array($configuredTable)) {
            $configuredRows = explode(':', $configuredTable['key'])[1] ?? '-';
            $payloadColumn = (string) ($configuredTable['columns']['payload'] ?? '-');
            $configuredPayloadBytes = explode(':', $payloadColumn)[1] ?? '-';
        }

        try {
            $table = Octane::table($tableName);

            return [
                'configured_rows' => (string) $configuredRows,
                'configured_payload_bytes' => (string) $configuredPayloadBytes,
                'active_rows' => method_exists($table, 'count') ? (string) $table->count() : '-',
                'memory_size' => method_exists($table, 'getMemorySize') ? $this->formatBytes((int) $table->getMemorySize()) : '-',
                'status' => 'available',
            ];
        } catch (Throwable $throwable) {
            return [
                'configured_rows' => (string) $configuredRows,
                'configured_payload_bytes' => (string) $configuredPayloadBytes,
                'active_rows' => '-',
                'memory_size' => '-',
                'status' => 'unavailable: '.$throwable->getMessage(),
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

        return (string) $bytes;
    }
}
