<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Telemetry') — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, monospace; font-size: 14px; line-height: 1.5; color: #1a1a1a; background: #f8f9fa; padding: 20px; }
        a { color: #0969da; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #d0d7de; }
        .header h1 { font-size: 18px; font-weight: 600; }
        .nav { display: flex; gap: 16px; }
        .nav a { padding: 4px 8px; border-radius: 4px; font-size: 13px; font-weight: 500; }
        .nav a.active { background: #1a1a1a; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d0d7de; margin-bottom: 16px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #d0d7de; font-size: 13px; }
        th { background: #f6f8fa; font-weight: 600; white-space: nowrap; }
        tr:hover { background: #f6f8fa; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-open { background: #ffebe9; color: #cf222e; }
        .badge-resolved { background: #dafbe1; color: #116329; }
        .badge-muted { background: #eaeef2; color: #57606a; }
        .mono { font-family: 'SF Mono', Consolas, monospace; font-size: 12px; }
        .text-muted { color: #57606a; }
        .text-sm { font-size: 12px; }
        .btn { display: inline-block; padding: 6px 12px; border: 1px solid #d0d7de; border-radius: 4px; font-size: 13px; font-weight: 500; background: #fff; color: #1a1a1a; cursor: pointer; }
        .btn:hover { background: #f6f8fa; text-decoration: none; }
        .btn-sm { padding: 3px 8px; font-size: 12px; }
        .btn-danger { background: #cf222e; color: #fff; border-color: #cf222e; }
        .btn-danger:hover { background: #a40e26; }
        .btn-success { background: #1a7f37; color: #fff; border-color: #1a7f37; }
        .btn-success:hover { background: #116329; }
        .card { background: #fff; border: 1px solid #d0d7de; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .card h2 { font-size: 15px; margin-bottom: 12px; }
        pre { background: #161b22; color: #c9d1d9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 12px; line-height: 1.6; white-space: pre-wrap; word-break: break-all; }
        .pagination { display: flex; gap: 8px; justify-content: center; margin-top: 16px; }
        .pagination a, .pagination span { padding: 4px 10px; border: 1px solid #d0d7de; border-radius: 4px; font-size: 12px; }
        .pagination span { background: #1a1a1a; color: #fff; border-color: #1a1a1a; }
        .count-badge { display: inline-block; min-width: 24px; padding: 2px 6px; text-align: center; border-radius: 10px; background: #eaeef2; font-size: 11px; font-weight: 600; }
        .flex { display: flex; }
        .flex-between { justify-content: space-between; align-items: center; }
        .gap-8 { gap: 8px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-16 { margin-bottom: 16px; }
        .mt-16 { margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Telemetry — {{ config('app.name') }}</h1>
            <nav class="nav">
                <a href="{{ route('accelerator.telemetry.index') }}" class="{{ request()->routeIs('accelerator.telemetry.index') ? 'active' : '' }}">Exceptions</a>
                <a href="{{ route('accelerator.telemetry.logs') }}" class="{{ request()->routeIs('accelerator.telemetry.logs') ? 'active' : '' }}">Logs</a>
                <a href="{{ url('/admin') }}" class="">← Admin</a>
            </nav>
        </div>

        @yield('content')
    </div>
</body>
</html>
