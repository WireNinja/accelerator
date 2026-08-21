@php
    $user = filament()->auth()->user();
    $panelEnum = config('accelerator.enums.panel');
    $panelMetadata = is_string($panelEnum) && enum_exists($panelEnum)
        ? collect($panelEnum::cases())->keyBy(fn (BackedEnum $panel): string|int => $panel->value)
        : collect();
    $panels = collect(filament()->getPanels())
        ->filter(fn (\Filament\Panel $panel): bool => $user instanceof \Filament\Models\Contracts\FilamentUser
            ? $user->canAccessPanel($panel)
            : $panel->getId() === filament()->getId())
        ->mapWithKeys(function (\Filament\Panel $panel) use ($panelMetadata): array {
            $metadata = $panelMetadata->get($panel->getId());
            $label = $metadata instanceof \Filament\Support\Contracts\HasLabel ? $metadata->getLabel() : null;

            return [$panel->getId() => [
                'label' => filled($label) ? (string) $label : str($panel->getId())->headline()->toString(),
                'url' => $panel->getUrl(),
            ]];
        })
        ->filter(fn (array $panel): bool => filled($panel['url']));
@endphp

@if ($panels->count() > 1)
    <x-filament::input.wrapper>
        <x-filament::input.select
            aria-label="Ganti panel"
            x-on:change="window.location.assign($event.target.value)"
        >
            @foreach ($panels as $panelId => $panel)
                <option
                    value="{{ $panel['url'] }}"
                    @selected(filament()->getId() === $panelId)
                >
                    {{ $panel['label'] }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
@endif
