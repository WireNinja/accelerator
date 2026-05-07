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
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    <div class="min-h-screen min-h-[100dvh] w-full flex items-center justify-center p-4 lg:p-8 bg-gray-100 dark:bg-gray-950">
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

        <!-- Main Container -->
        <div class="w-full max-w-[1200px] lg:h-[760px] min-h-[600px] bg-[#272320] rounded-[32px] shadow-[0_30px_60px_rgba(0,0,0,0.15)] flex flex-col lg:flex-row overflow-hidden relative">
            
        <!-- Left Image Panel (Visible on large screens) -->
        <div class="hidden lg:flex lg:w-[45%] h-full relative p-12 flex-col text-white overflow-hidden">
            
            @if (filled($simplePageImageUrl))
                <img class="absolute inset-0 h-full w-full object-cover" 
                     src="{{ $simplePageImageUrl }}" 
                     alt="Background">
                <!-- Overlay to blend with brand color -->
                <div class="absolute inset-0 bg-black/40"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
            @endif
                <svg class="absolute inset-0 w-full h-full pointer-events-none opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                    <!-- Crosshair lines -->
                    <line x1="50%" y1="0" x2="50%" y2="100%" stroke="white" stroke-width="1.5" />
                    <line x1="0" y1="35%" x2="100%" y2="35%" stroke="white" stroke-width="1.5" />
                    <!-- Concentric Circles -->
                    <circle cx="50%" cy="35%" r="120" fill="none" stroke="white" stroke-width="1.5" />
                    <circle cx="50%" cy="35%" r="240" fill="none" stroke="white" stroke-width="1.5" />
                </svg>

                <!-- Top Text -->
                <p class="text-[11px] font-light tracking-wide text-white/70 relative z-10 mt-2">
                    {{ resolve(\WireNinja\Accelerator\Settings\SystemSettings::class)->simple_page_subtitle ?? 'Internal System – online solutions for your workspace.' }}
                </p>

                <!-- Main Title -->
                <div class="w-full flex justify-center mt-32 relative z-10">
                    <h1 class="text-[52px] leading-[1.05] font-semibold text-center tracking-tight drop-shadow-lg">
                        {!! nl2br(e(resolve(\WireNinja\Accelerator\Settings\SystemSettings::class)->simple_page_title ?? 'Manage\nyour workspace')) !!}
                    </h1>
                </div>
            </div>

            <!-- ================= RIGHT SECTION (White) ================= -->
            <!-- Memiliki border radius kiri yang menutupi bagian dark background -->
            <div class="w-full lg:w-[55%] h-full bg-white dark:bg-gray-900 lg:rounded-l-[48px] relative z-20 flex flex-col px-6 lg:px-16 py-8 lg:py-12 justify-between">
                
                <header class="flex justify-between items-center w-full mb-8 lg:mb-0">
                    <!-- Brand -->
                    <div class="flex items-center gap-2.5">
                        @if (filled(filament()->getBrandLogo()))
                            <x-filament-panels::logo />
                        @else
                            <span class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                                {{ filament()->getBrandName() }}
                            </span>
                        @endif
                    </div>

                    @if (($hasTopbar ?? true) && filament()->auth()->check())
                        <div class="flex items-center justify-end gap-4">
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

                <!-- Main Login Form Container -->
                <main class="w-full max-w-[420px] mx-auto flex flex-col justify-center flex-grow -mt-8">
                    {{ $slot }}
                </main>

                <!-- Bottom Footer -->
                <footer class="flex justify-between items-center mt-8 lg:mt-0 text-[12px] text-gray-400 font-medium">
                    <span>&copy; {{ date('Y') }} {{ filament()->getBrandName() }}</span>
                </footer>
            </div>
            
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
    </div>
</x-filament-panels::layout.base>
