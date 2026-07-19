<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Http\Controllers\Insider;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use WireNinja\Accelerator\Contracts\AcceleratorUser;
use WireNinja\Accelerator\Support\UserModel;

class InsiderDashboardController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorizeAccess();

        $content = <<<'HTML'
        <h1>Insider Runtime Panel</h1>
        <p><b>Internal only.</b> Endpoint ini hanya untuk super admin.</p>

        <div class="bento-grid">
            <div class="bento-card">
                <div class="bento-title">Session & Identity</div>
                <div class="bento-desc">Lihat detail data sesi aktif, detail request, metadata tabel sesi Octane, serta lakukan manipulasi data sesi.</div>
                <a href="/insider/sessions" class="bento-btn">Manage Sessions</a>
            </div>

            <div class="bento-card">
                <div class="bento-title">OPCache Debugger</div>
                <div class="bento-desc">Analisis statistik alokasi memori Zend OPCache, status restart, direktif konfigurasi, dan daftar script php yang ter-cache.</div>
                <a href="/insider/debug-opcache" class="bento-btn">Debug OPCache</a>
            </div>

            <div class="bento-card">
                <div class="bento-title">System Diagnostics</div>
                <div class="bento-desc">Statistik performa real-time, runtime PHP, penggunaan memory/GC, info JIT Compiler, dan daftar extensi yang terinstal.</div>
                <a href="/insider/stats" class="bento-btn">View Diagnostics</a>
            </div>
        </div>

        <br><br>
        HTML;

        // Add application runtime table for quick overview
        $runtimeTable = $this->renderTable('System Overview', [
            ['App Name', (string) config('app.name')],
            ['Laravel Version', app()->version()],
            ['App Env', (string) config('app.env')],
            ['PHP Version', PHP_VERSION],
            ['PHP SAPI', PHP_SAPI],
            ['Running In Octane', isset($_SERVER['LARAVEL_OCTANE']) ? 'YES' : 'NO'],
        ]);

        return $this->renderPage('Insider Diagnostics Dashboard', $content.$runtimeTable);
    }

    private function authorizeAccess(): AcceleratorUser
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
                
                .bento-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    max-width: 1200px;
                    margin-top: 30px;
                    margin-bottom: 30px;
                }
                .bento-card {
                    background: white;
                    padding: 24px;
                    border-radius: 12px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    transition: transform 0.2s, box-shadow 0.2s;
                    border: 1px solid #e2e8f0;
                    text-align: left;
                }
                .bento-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
                }
                .bento-title {
                    font-size: 1.25rem;
                    font-weight: 600;
                    color: #0f172a;
                    margin-bottom: 8px;
                }
                .bento-desc {
                    color: #64748b;
                    font-size: 0.875rem;
                    line-height: 1.5;
                    margin-bottom: 20px;
                    height: 60px;
                }
                .bento-btn {
                    display: inline-block;
                    background: #2563eb;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 8px;
                    font-weight: 500;
                    text-decoration: none;
                    transition: background 0.15s ease;
                }
                .bento-btn:hover {
                    background: #1d4ed8;
                    text-decoration: none;
                    color: white;
                }
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
}
