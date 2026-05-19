@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $systemSettings = resolve(\WireNinja\Accelerator\Settings\SystemSettings::class);
    $simplePageImage = $systemSettings->simple_page_image;
    $simplePageImageUrl = null;

    if (filled($simplePageImage)) {
        if (str($simplePageImage)->startsWith(['http://', 'https://'])) {
            $simplePageImageUrl = $simplePageImage;
        } elseif (str($simplePageImage)->startsWith('/')) {
            $simplePageImageUrl = url($simplePageImage);
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($simplePageImage)) {
            $simplePageImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($simplePageImage);
        }
    }

    $renderHookScopes = $livewire?->getRenderHookScopes();
    $maxContentWidth ??= (filament()->getSimplePageMaxContentWidth() ?? Width::Large);

    if (is_string($maxContentWidth)) {
        $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
    }

    $brandLogo = filament()->getBrandLogo();
    $brandLogoHeight = filament()->getBrandLogoHeight() ?? '1.5rem';
    $brandName = filament()->getBrandName();

    // Get current layout (fallback to left_reveal)
    $layout = $systemSettings->simple_page_layout ?? \WireNinja\Accelerator\Enums\LoginLayoutEnum::LeftReveal;
    $layoutVal = $layout instanceof \BackedEnum ? $layout->value : (string) $layout;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <style>
        /* ── Kill Filament's white body / simple-layout wrapper ── */
        html, body {
            background-color: #030712 !important;
            overflow: hidden !important;
        }
        .fi-simple-layout,
        .fi-simple-page,
        .fi-simple-main {
            background: transparent !important;
            padding: 0 !important;
            min-height: unset !important;
            height: 100% !important;
            overflow: hidden !important;
        }

        /* ── Filament input / label overrides scoped to login ── */
        /* actual rendered class for input labels */
        .fi-fo-field-label-content,
        .fi-fo-field-wrp-label,
        .fi-fo-field-wrp-label label,
        [class*="fi-fo"] label {
            color: rgba(255,255,255,0.8) !important;
        }
        .fi-input {
            background-color: rgba(255,255,255,0.08) !important;
            border-color: rgba(255,255,255,0.15) !important;
            color: #fff !important;
        }
        .fi-input::placeholder {
            color: rgba(255,255,255,0.35) !important;
        }
        .fi-input-wrp {
            background-color: rgba(255,255,255,0.06) !important;
            border-color: rgba(255,255,255,0.15) !important;
        }
        .fi-input-wrp:focus-within {
            outline-color: rgba(255,255,255,0.5) !important;
            border-color: rgba(255,255,255,0.35) !important;
        }
        a,
        .fi-btn-color-gray {
            color: rgba(255,255,255,0.55) !important;
        }
        a:hover {
            color: #fff !important;
        }
        .fi-checkbox-label,
        .fi-fo-field-wrp-hint {
            color: rgba(255,255,255,0.55) !important;
        }
        /* ── Submit / primary button override ── */
        .fi-btn.fi-color-primary,
        .fi-btn.fi-color.fi-color-primary {
            background-color: #fff !important;
            color: #030712 !important;
            border-color: transparent !important;
        }
        .fi-btn.fi-color-primary:hover {
            background-color: rgba(255,255,255,0.88) !important;
        }
        /* ── Suffix action separator (eye icon divider) ── */
        .fi-input-wrp .fi-input-suffix-action,
        .fi-input-wrp [data-suffix],
        .fi-input-wrp > *:last-child {
            border-color: rgba(255,255,255,0.12) !important;
        }
        .fi-input-wrp:not(:focus-within) .fi-input-suffix-action,
        .fi-input-wrp:not(:focus-within) > *:last-child {
            border-color: rgba(255,255,255,0.12) !important;
        }

        /* ── Blob animations for Glassmorphism ── */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob {
            animation: blob 15s infinite alternate ease-in-out;
        }
        .animation-delay-2000 {
            animation-delay: 2s;
        }
        .animation-delay-4000 {
            animation-delay: 4s;
        }

        /* ── Stark Gridlines Pattern ── */
        .stark-grid {
            background-size: 40px 40px;
            background-image: 
                linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
            mask-image: radial-gradient(ellipse 60% 60% at 50% 50%, #000 60%, transparent 100%);
            -webkit-mask-image: radial-gradient(ellipse 60% 60% at 50% 50%, #000 60%, transparent 100%);
        }
    </style>

    {{-- Full-viewport locked wrapper (no scroll) --}}
    <div class="fixed inset-0 bg-gray-950 overflow-hidden flex flex-row">

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        {{-- Background Layer based on layout --}}
        @if ($layoutVal === 'minimal_stark')
            <div class="absolute inset-0 stark-grid opacity-60"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-white/[0.01] blur-[120px] rounded-full pointer-events-none"></div>
        @elseif ($layoutVal === 'glassmorphism')
            @if (filled($simplePageImageUrl))
                <img class="absolute inset-0 h-full w-full object-cover" src="{{ $simplePageImageUrl }}" alt="">
                <div class="absolute inset-0 bg-slate-950/20 pointer-events-none"></div>
            @endif
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-[40%] -left-[20%] w-[80%] h-[80%] rounded-full bg-indigo-500/15 blur-[120px] animate-blob"></div>
                <div class="absolute -bottom-[40%] -right-[20%] w-[80%] h-[80%] rounded-full bg-purple-600/15 blur-[120px] animate-blob animation-delay-2000"></div>
                <div class="absolute top-[30%] left-[30%] w-[60%] h-[60%] rounded-full bg-violet-500/15 blur-[120px] animate-blob animation-delay-4000"></div>
            </div>
        @else
            @if (filled($simplePageImageUrl))
                <img class="absolute inset-0 h-full w-full object-cover" src="{{ $simplePageImageUrl }}" alt="">
            @else
                <svg class="absolute inset-0 w-full h-full opacity-[0.04]" xmlns="http://www.w3.org/2000/svg">
                    <line x1="50%" y1="0" x2="50%" y2="100%" stroke="white" stroke-width="1" />
                    <line x1="0" y1="50%" x2="100%" y2="50%" stroke="white" stroke-width="1" />
                    <circle cx="50%" cy="50%" r="200" fill="none" stroke="white" stroke-width="1" />
                    <circle cx="50%" cy="50%" r="400" fill="none" stroke="white" stroke-width="1" />
                </svg>
            @endif
        @endif

        {{-- Mobile overlay --}}
        @if ($layoutVal !== 'minimal_stark')
            <div class="absolute inset-0 bg-gray-950/80 lg:hidden pointer-events-none z-10"></div>
        @endif

        {{-- Desktop gradients for reveal layouts --}}
        @if ($layoutVal === 'left_reveal')
            <div class="absolute inset-0 hidden lg:block pointer-events-none z-10" style="background: linear-gradient(to right, #030712 0%, #030712 35%, rgba(3,7,18,0.88) 50%, rgba(3,7,18,0.55) 65%, rgba(3,7,18,0.15) 80%, transparent 100%);"></div>
        @elseif ($layoutVal === 'right_reveal')
            <div class="absolute inset-0 hidden lg:block pointer-events-none z-10" style="background: linear-gradient(to left, #030712 0%, #030712 35%, rgba(3,7,18,0.88) 50%, rgba(3,7,18,0.55) 65%, rgba(3,7,18,0.15) 80%, transparent 100%);"></div>
        @endif

        {{-- Watermark logo for reveal layouts --}}
        @if ($layoutVal === 'left_reveal' || $layoutVal === 'right_reveal')
            <div class="absolute bottom-8 z-10 pointer-events-none select-none @if($layoutVal === 'left_reveal') right-10 @else left-10 @endif">
                @if (filled($brandLogo))
                    @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                        <div style="height: 6rem">{{ $brandLogo }}</div>
                    @else
                        <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="object-contain" style="height: 6rem">
                    @endif
                @else
                    <span class="text-[52px] font-bold tracking-tighter text-white/10 leading-none">
                        {{ $brandName }}
                    </span>
                @endif
            </div>
        @endif

        {{-- LAYOUT STRUCTURING --}}
        @if ($layoutVal === 'left_reveal' || $layoutVal === 'right_reveal')
            <div class="relative z-20 h-full flex flex-col w-full max-w-[480px] px-8 lg:px-12 py-10 overflow-hidden @if($layoutVal === 'right_reveal') ml-auto @endif">
                <header class="flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        @if (filled($brandLogo))
                            @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                <div class="shrink-0 text-white" style="height: 1.75rem">{{ $brandLogo }}</div>
                            @else
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="shrink-0 object-contain" style="height: 1.75rem">
                            @endif
                        @endif
                        <span class="text-lg font-bold tracking-tight text-white leading-none">{{ $brandName }}</span>
                    </div>
                </header>

                <main class="flex-1 flex flex-col justify-center min-h-0">
                    {{ $slot }}
                </main>

                <footer class="shrink-0 text-[11px] text-white/30 font-medium">
                    &copy; {{ date('Y') }} {{ $brandName }}
                </footer>
            </div>

        @elseif ($layoutVal === 'split_screen')
            <div class="relative z-20 h-full w-full grid grid-cols-1 lg:grid-cols-2">
                <!-- Form Side -->
                <div class="flex flex-col justify-between h-full px-8 lg:px-16 py-10 bg-gray-950">
                    <header class="flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            @if (filled($brandLogo))
                                @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                    <div class="shrink-0 text-white" style="height: 1.75rem">{{ $brandLogo }}</div>
                                @else
                                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="shrink-0 object-contain" style="height: 1.75rem">
                                @endif
                            @endif
                            <span class="text-lg font-bold tracking-tight text-white leading-none">{{ $brandName }}</span>
                        </div>
                    </header>

                    <main class="flex-1 flex flex-col justify-center min-h-0 py-8">
                        <div class="w-full max-w-[400px] mx-auto">
                            {{ $slot }}
                        </div>
                    </main>

                    <footer class="shrink-0 text-[11px] text-white/30 font-medium">
                        &copy; {{ date('Y') }} {{ $brandName }}
                    </footer>
                </div>

                <!-- Image Side -->
                <div class="relative hidden lg:block overflow-hidden h-full w-full">
                    @if (filled($simplePageImageUrl))
                        <img class="absolute inset-0 h-full w-full object-cover" src="{{ $simplePageImageUrl }}" alt="">
                        <div class="absolute inset-0 bg-gradient-to-r from-gray-950 via-transparent to-transparent opacity-40"></div>
                    @else
                        <div class="absolute inset-0 bg-gray-900 flex items-center justify-center">
                            <svg class="w-24 h-24 text-white/10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                    @endif
                </div>
            </div>

        @elseif ($layoutVal === 'split_inset')
            <div class="relative z-20 h-full w-full grid grid-cols-1 lg:grid-cols-2">
                <!-- Form Side -->
                <div class="flex flex-col justify-between h-full px-8 lg:px-16 py-10 bg-gray-950">
                    <header class="flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            @if (filled($brandLogo))
                                @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                    <div class="shrink-0 text-white" style="height: 1.75rem">{{ $brandLogo }}</div>
                                @else
                                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="shrink-0 object-contain" style="height: 1.75rem">
                                @endif
                            @endif
                            <span class="text-lg font-bold tracking-tight text-white leading-none">{{ $brandName }}</span>
                        </div>
                    </header>

                    <main class="flex-1 flex flex-col justify-center min-h-0 py-8">
                        <div class="w-full max-w-[400px] mx-auto">
                            {{ $slot }}
                        </div>
                    </main>

                    <footer class="shrink-0 text-[11px] text-white/30 font-medium">
                        &copy; {{ date('Y') }} {{ $brandName }}
                    </footer>
                </div>

                <!-- Image Side with Inset & Rounded corners -->
                <div class="hidden lg:block h-full w-full p-4 xl:p-6 bg-gray-950">
                    <div class="relative h-full w-full overflow-hidden rounded-2xl xl:rounded-3xl border border-white/10 shadow-2xl">
                        @if (filled($simplePageImageUrl))
                            <img class="absolute inset-0 h-full w-full object-cover" src="{{ $simplePageImageUrl }}" alt="">
                            <div class="absolute inset-0 bg-gradient-to-r from-gray-950/20 via-transparent to-transparent"></div>
                        @else
                            <div class="absolute inset-0 bg-gray-900 flex items-center justify-center">
                                <svg class="w-24 h-24 text-white/10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        @elseif ($layoutVal === 'glassmorphism')
            <div class="relative z-20 h-full w-full flex items-center justify-center p-4 md:p-8 overflow-y-auto">
                <div class="w-full max-w-[460px] bg-white/[0.05] backdrop-blur-lg border border-white/20 rounded-2xl p-8 lg:p-10 shadow-[0_24px_50px_-12px_rgba(0,0,0,0.5),_inset_0_1px_1px_rgba(255,255,255,0.15)] flex flex-col">
                    <header class="flex flex-col items-center gap-3 mb-6">
                        @if (filled($brandLogo))
                            @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                <div class="text-white mb-2" style="height: 2.5rem">{{ $brandLogo }}</div>
                            @else
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="object-contain mb-2" style="height: 2.5rem">
                            @endif
                        @endif
                        <span class="text-xl font-bold tracking-tight text-white">{{ $brandName }}</span>
                    </header>

                    <main class="flex-1 min-h-0">
                        {{ $slot }}
                    </main>

                    <footer class="mt-8 text-[11px] text-white/30 font-medium text-center">
                        &copy; {{ date('Y') }} {{ $brandName }}
                    </footer>
                </div>
            </div>

        @elseif ($layoutVal === 'minimal_stark')
            <div class="relative z-20 h-full w-full flex items-center justify-center p-4 md:p-8 overflow-y-auto">
                <div class="w-full max-w-[400px] flex flex-col">
                    <header class="flex flex-col items-center gap-3 mb-8">
                        @if (filled($brandLogo))
                            @if ($brandLogo instanceof \Illuminate\Contracts\Support\Htmlable)
                                <div class="text-white" style="height: 2rem">{{ $brandLogo }}</div>
                            @else
                                <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="object-contain" style="height: 2rem">
                            @endif
                        @endif
                        <span class="text-lg font-semibold tracking-tight text-gray-200">{{ $brandName }}</span>
                    </header>

                    <main class="flex-1 min-h-0">
                        {{ $slot }}
                    </main>

                    <footer class="mt-12 text-[10px] text-gray-500 font-medium text-center tracking-widest uppercase">
                        &copy; {{ date('Y') }} {{ $brandName }}
                    </footer>
                </div>
            </div>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}

    </div>
</x-filament-panels::layout.base>
