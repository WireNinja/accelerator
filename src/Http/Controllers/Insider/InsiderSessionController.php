<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Insider;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;
use Laravel\Octane\Facades\Octane;
use Throwable;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Support\Cast;
use WireNinja\Accelerator\Support\UserModel;

class InsiderSessionController extends Controller
{
    public function index(Request $request): string
    {
        $user = $this->authorizeAccess();

        $sessionData = $request->session()->all();
        $sessionTableStats = $this->sessionTableStats();
        $feedback = $this->renderFeedback();

        $content = [
            '<h1>Insider Session & Identity</h1>',
            $feedback,
            $this->renderTable('Identity & Access', [
                ['User ID', Cast::asString($user->getAuthIdentifier())],
                ['Name', Cast::asString($user->getAttribute('name'))],
                ['Email', Cast::asString($user->getAttribute('email'))],
                ['Roles', $user->getRoleNames()->implode(', ') ?: '-'],
                ['Super Admin', $user->isSuperAdmin() ? 'YES' : 'NO'],
                ['Verified Email', $user->hasVerifiedEmail() ? 'YES' : 'NO'],
                ['Suspended', $user->isSuspended() ? 'YES' : 'NO'],
            ]),
            $this->renderTable('Request Details', [
                ['Method', $request->method()],
                ['Full URL', $request->fullUrl()],
                ['Path', $request->path()],
                ['Host', $request->getHost()],
                ['Scheme', $request->getScheme()],
                ['IP', $request->ip() ?? '-'],
                ['User Agent', (string) $request->userAgent()],
                ['Referer', (string) $request->headers->get('referer', '-')],
            ]),
            $this->renderTable('Session Details', [
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
            $this->renderTable('Mutation Actions', [
                ['Update Session', $this->renderUpdateSessionForm($request)],
                ['Regenerate Session ID', $this->renderActionForm('/insider/sessions/regenerate', 'Regenerate Session ID', $request)],
                ['Logout', $this->renderActionForm('/logout', 'Logout', $request)],
            ]),
            '<h2>Full Session Dump</h2>',
            '<pre>'.e(var_export($sessionData, true)).'</pre>',
        ];

        return $this->renderPage('Insider Session & Identity', implode('', $content));
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

        return redirect('/insider/sessions')->with('status', 'Session updated.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $request->session()->regenerate();

        return redirect('/insider/sessions')->with('status', 'Session ID regenerated.');
    }

    private function authorizeAccess(): Model&AcceleratorUser
    {
        $user = UserModel::current();

        abort_unless($user->isSuperAdmin(), 403);

        return $user;
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
                pre { background: #1e293b; color: #f8fafc; padding: 16px; border-radius: 8px; overflow: auto; max-width: 1200px; font-size: 13px; line-height: 1.6; }
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
        <form method="POST" action="/insider/sessions/store">
            {$csrf}
            <table border="1" cellpadding="4" cellspacing="0" style="margin-bottom: 0; box-shadow: none; border-radius: 0;">
                <tr>
                    <td><textarea name="note" rows="8" cols="100" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; font-family: monospace;">{$note}</textarea></td>
                </tr>
                <tr>
                    <td align="center"><input type="submit" value="Update Session" style="background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;"></td>
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
            <input type="submit" value="{$label}" style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500;">
        </form>
        HTML;
    }

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
                'active_rows' => (string) $table->count(),
                'memory_size' => $this->formatBytes((int) $table->getMemorySize()),
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
