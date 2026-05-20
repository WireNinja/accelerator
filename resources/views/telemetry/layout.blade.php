<!DOCTYPE html>
<html lang="en" class="bg-[#171717]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Telemetry') - {{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }

        body {
            background: #171717;
        }

        .shiki,
        .shiki span {
            background: transparent !important;
        }

        .shiki {
            overflow: visible;
            padding: .75rem 0;
        }

        .shiki .line {
            display: block;
            min-height: 1.75rem;
            padding-left: 4.25rem;
            padding-right: 1rem;
            position: relative;
        }

        .shiki .line::before {
            color: rgb(115 115 115);
            content: attr(data-line);
            left: 0;
            padding-right: 1rem;
            position: absolute;
            text-align: right;
            user-select: none;
            width: 3.25rem;
        }

        .shiki .line.is-focused {
            background: rgba(190, 18, 60, .72) !important;
            color: white;
        }
    </style>
</head>
<body class="min-h-screen text-neutral-100 antialiased">
    <div class="mx-auto flex min-h-screen w-full max-w-[94rem] flex-col border-x border-white/[8%] px-5 py-6 sm:px-8 lg:px-14">
        <header class="mb-12 flex items-center justify-between gap-4">
            <a href="{{ route('accelerator.telemetry.index') }}" class="flex items-center gap-2 text-sm text-neutral-200">
                <span class="flex size-5 items-center justify-center rounded-full bg-rose-500 text-[11px] font-bold text-white">!</span>
                <span>Accelerator Telemetry</span>
            </a>

            <nav class="flex flex-wrap items-center gap-2 text-sm">
                <a
                    href="{{ route('accelerator.telemetry.index') }}"
                    class="rounded-md border px-3 py-1.5 {{ request()->routeIs('accelerator.telemetry.index') || request()->routeIs('accelerator.telemetry.show') ? 'border-white/15 bg-white/10 text-white' : 'border-white/10 bg-white/[3%] text-neutral-400 hover:bg-white/[8%] hover:text-white' }}"
                >
                    Exceptions
                </a>
                <a
                    href="{{ route('accelerator.telemetry.logs') }}"
                    class="rounded-md border px-3 py-1.5 {{ request()->routeIs('accelerator.telemetry.logs') ? 'border-white/15 bg-white/10 text-white' : 'border-white/10 bg-white/[3%] text-neutral-400 hover:bg-white/[8%] hover:text-white' }}"
                >
                    Logs
                </a>
                <a href="{{ url('/admin') }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-400 hover:bg-white/[8%] hover:text-white">
                    Admin
                </a>
            </nav>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
