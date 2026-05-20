<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Telemetry') - {{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-zinc-50 text-zinc-950 antialiased">
    <div class="mx-auto flex min-h-screen max-w-7xl flex-col gap-5 px-4 py-5 sm:px-6 lg:px-8">
        <header class="flex flex-col gap-3 border-b border-zinc-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Accelerator</p>
                <h1 class="text-xl font-semibold tracking-normal text-zinc-950">Telemetry</h1>
            </div>

            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a
                    href="{{ route('accelerator.telemetry.index') }}"
                    class="rounded-md px-3 py-1.5 font-medium {{ request()->routeIs('accelerator.telemetry.index') || request()->routeIs('accelerator.telemetry.show') ? 'bg-zinc-950 text-white' : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100' }}"
                >
                    Exceptions
                </a>
                <a
                    href="{{ route('accelerator.telemetry.logs') }}"
                    class="rounded-md px-3 py-1.5 font-medium {{ request()->routeIs('accelerator.telemetry.logs') ? 'bg-zinc-950 text-white' : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100' }}"
                >
                    Logs
                </a>
                <a href="{{ url('/admin') }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">
                    Admin
                </a>
            </nav>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>
    </div>
</body>
</html>
