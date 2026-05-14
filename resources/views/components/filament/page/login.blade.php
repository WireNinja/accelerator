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
            <div class="mb-8 text-left">
                @if (filled($heading))
                    <h2 class="text-[36px] leading-tight font-semibold tracking-tight text-white">
                        {{ $heading }}
                    </h2>
                @endif
                
                @if (filled($subheading))
                    <p class="mt-2 text-sm text-white/50">
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
