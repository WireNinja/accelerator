@props([
    'heading' => null,
    'subheading' => null,
])

@php
    $heading ??= $this->getHeading();
    $subheading ??= $this->getSubHeading();
    $hasLogo = $this->hasLogo();
@endphp

<div {{ $attributes->class(['w-full']) }}>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <div class="w-full flex flex-col">
        @if (filled($heading) || filled($subheading))
            <div class="mb-10 text-left">
                @if (filled($heading))
                    <h2 class="text-[40px] leading-tight font-medium tracking-tight text-gray-900 dark:text-white">
                        {{ $heading }}
                    </h2>
                @endif
                
                @if (filled($subheading))
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $subheading }}
                    </p>
                @endif
            </div>
        @endif

        <div class="w-full mt-4">
            {{ $slot }}
        </div>
    </div>

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals />
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
