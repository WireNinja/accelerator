@php
    use Filament\Support\Enums\Width;

    $livewire ??= null;

    $simplePageImage = resolve(\WireNinja\Accelerator\Settings\SystemSettings::class)->simple_page_image;
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
        .acl-login-panel .fi-fo-field-label-content,
        .acl-login-panel .fi-fo-field-wrp-label,
        .acl-login-panel .fi-fo-field-wrp-label label,
        .acl-login-panel [class*="fi-fo"] label {
            color: rgba(255,255,255,0.8) !important;
        }
        .acl-login-panel .fi-input {
            background-color: rgba(255,255,255,0.08) !important;
            border-color: rgba(255,255,255,0.15) !important;
            color: #fff !important;
        }
        .acl-login-panel .fi-input::placeholder {
            color: rgba(255,255,255,0.35) !important;
        }
        .acl-login-panel .fi-input-wrp {
            background-color: rgba(255,255,255,0.06) !important;
            border-color: rgba(255,255,255,0.15) !important;
        }
        .acl-login-panel .fi-input-wrp:focus-within {
            outline-color: rgba(255,255,255,0.5) !important;
            border-color: rgba(255,255,255,0.35) !important;
        }
        .acl-login-panel a,
        .acl-login-panel .fi-btn-color-gray {
            color: rgba(255,255,255,0.55) !important;
        }
        .acl-login-panel a:hover {
            color: #fff !important;
        }
        .acl-login-panel .fi-checkbox-label,
        .acl-login-panel .fi-fo-field-wrp-hint {
            color: rgba(255,255,255,0.55) !important;
        }
        /* ── Submit / primary button override ── */
        .acl-login-panel .fi-btn.fi-color-primary,
        .acl-login-panel .fi-btn.fi-color.fi-color-primary {
            background-color: #fff !important;
            color: #030712 !important;
            border-color: transparent !important;
        }
        .acl-login-panel .fi-btn.fi-color-primary:hover {
            background-color: rgba(255,255,255,0.88) !important;
        }
        /* ── Suffix action separator (eye icon divider) ── */
        /* Filament adds border-l on the suffix action container.
           Keep it subtle — only show when the wrapper is focused. */
        .acl-login-panel .fi-input-wrp .fi-input-suffix-action,
        .acl-login-panel .fi-input-wrp [data-suffix],
        .acl-login-panel .fi-input-wrp > *:last-child {
            border-color: rgba(255,255,255,0.12) !important;
        }
        .acl-login-panel .fi-input-wrp:not(:focus-within) .fi-input-suffix-action,
        .acl-login-panel .fi-input-wrp:not(:focus-within) > *:last-child {
            border-color: rgba(255,255,255,0.12) !important;
        }
    </style>

    {{-- Full-viewport locked wrapper (no scroll) --}}
    <div class="fixed inset-0 bg-gray-950 overflow-hidden">

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        {{-- Background image --}}
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

        {{-- Mobile: flat dark overlay so text is always readable --}}
        <div class="absolute inset-0 bg-gray-950/80 lg:hidden pointer-events-none"></div>
        {{-- Desktop: smooth left-to-right multi-stop gradient --}}
        <div class="absolute inset-0 hidden lg:block pointer-events-none" style="background: linear-gradient(to right, #030712 0%, #030712 35%, rgba(3,7,18,0.88) 50%, rgba(3,7,18,0.55) 65%, rgba(3,7,18,0.15) 80%, transparent 100%);"></div>

        {{-- BOTTOM-RIGHT: logo watermark, fixed to viewport --}}
        <div class="absolute bottom-8 right-10 z-10 pointer-events-none select-none">
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

        {{-- FORM COLUMN: occupies left side, full viewport height, no scroll --}}
        <div class="relative z-20 h-full flex flex-col w-full max-w-[480px] px-8 lg:px-12 py-10 acl-login-panel overflow-hidden">

            {{-- TOP-LEFT: logo + brand name --}}
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

                @if (($hasTopbar ?? true) && filament()->auth()->check())
                    <div class="flex items-center gap-4">
                        @if (filament()->hasDatabaseNotifications())
                            @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                                'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                                'position' => \Filament\Enums\DatabaseNotificationsPosition::Topbar,
                            ])
                        @endif
                        @if (filament()->hasUserMenu())
                            @livewire(Filament\Livewire\SimpleUserMenu::class)
                        @endif
                    </div>
                @endif
            </header>

            {{-- CENTRE: form, vertically centred in remaining space --}}
            <main class="flex-1 flex flex-col justify-center min-h-0">
                {{ $slot }}
            </main>

            {{-- BOTTOM-LEFT: copyright --}}
            <footer class="shrink-0 text-[11px] text-white/30 font-medium">
                &copy; {{ date('Y') }} {{ $brandName }}
            </footer>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}

    </div>
</x-filament-panels::layout.base>
