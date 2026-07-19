<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Telemetry') · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: dark; --bg:#101010; --panel:#181818; --soft:#222; --line:#303030; --text:#f5f5f5; --muted:#a3a3a3; --danger:#fb7185; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--text); font:14px/1.55 ui-sans-serif,system-ui,sans-serif; }
        a { color:inherit; }
        code, pre, .mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; }
        .shell { width:min(1400px,100%); min-height:100vh; margin:auto; padding:28px clamp(18px,4vw,56px) 60px; border-inline:1px solid #222; }
        .topbar,.row,.actions { display:flex; align-items:center; gap:12px; }
        .topbar { justify-content:space-between; margin-bottom:48px; }
        .brand { text-decoration:none; font-weight:650; }
        .brand span { display:inline-grid; place-items:center; width:22px; height:22px; margin-right:8px; border-radius:50%; background:var(--danger); color:#111; }
        .button,button,select { border:1px solid var(--line); border-radius:7px; background:var(--soft); color:var(--text); padding:7px 11px; font:inherit; }
        .button { display:inline-block; text-decoration:none; }
        button,select { cursor:pointer; }
        .button:hover,button:hover,select:hover { border-color:#555; }
        .muted { color:var(--muted); }
        .eyebrow { margin:0 0 6px; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.08em; }
        h1 { margin:0; font-size:clamp(26px,4vw,38px); line-height:1.15; }
        h2 { margin:0 0 14px; font-size:17px; }
        p { margin:0; }
        .stack { display:grid; gap:24px; }
        .card { border:1px solid var(--line); border-radius:12px; background:var(--panel); padding:18px; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; }
        .stat { border:1px solid var(--line); border-radius:9px; background:var(--panel); padding:14px; }
        .stat strong { display:block; margin-top:5px; font-size:19px; }
        .alert { border-color:#783544; color:#fecdd3; }
        .filters { display:flex; flex-wrap:wrap; gap:8px; }
        .filters .active { border-color:#777; background:#333; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:12px; }
        table { width:100%; border-collapse:collapse; background:var(--panel); }
        th,td { padding:13px 14px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        th { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
        tr:last-child td { border-bottom:0; }
        .issue { max-width:480px; }
        .issue a { font-weight:650; text-decoration:none; }
        .issue a:hover { text-decoration:underline; }
        .message { margin-top:5px; color:#d4d4d4; overflow-wrap:anywhere; }
        .badge { display:inline-block; border:1px solid var(--line); border-radius:999px; padding:2px 8px; font-size:12px; }
        .badge-open { border-color:#9f364d; color:#fda4af; }
        .badge-resolved { border-color:#276749; color:#86efac; }
        .badge-muted { color:#a3a3a3; }
        .pagination { display:flex; justify-content:center; align-items:center; gap:8px; }
        .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .definition { display:grid; grid-template-columns:140px minmax(0,1fr); gap:8px 16px; }
        .definition dt { color:var(--muted); }
        .definition dd { margin:0; overflow-wrap:anywhere; }
        pre { margin:0; max-width:100%; overflow:auto; border:1px solid var(--line); border-radius:9px; background:#0b0b0b; padding:14px; font-size:12px; line-height:1.55; white-space:pre-wrap; overflow-wrap:anywhere; }
        .source { white-space:pre; }
        .source-line { display:block; min-height:1.55em; padding-inline:7px; }
        .source-line.focus { background:#6f2537; }
        details { border:1px solid var(--line); border-radius:9px; background:var(--panel); }
        summary { cursor:pointer; padding:13px 15px; }
        details > div { padding:0 15px 15px; }
        .spacer { flex:1; }
        @media (max-width:760px) { .topbar { align-items:flex-start; } .grid-2 { grid-template-columns:1fr; } .definition { grid-template-columns:1fr; } th:nth-child(3),td:nth-child(3) { display:none; } }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a class="brand" href="{{ route('accelerator.telemetry.index') }}"><span>!</span>Accelerator Telemetry</a>
            <nav class="actions">
                <a class="button" href="{{ route('accelerator.telemetry.index') }}">Exceptions</a>
                <a class="button" href="{{ url('/admin') }}">Admin</a>
            </nav>
        </header>
        <main>@yield('content')</main>
    </div>
    @stack('scripts')
</body>
</html>
