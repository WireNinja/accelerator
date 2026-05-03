<?php

use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Js;

if (! function_exists('js_iconify')) {
    function js_iconify(): string
    {
        return Js::from(
            FilamentAsset::getScriptSrc('iconify', 'accelerator'),
        );
    }
}

// @TODO : decide if this is needed or not.
if (! function_exists('iconify')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function iconify(string $icon, array $attributes = []): string
    {
        $attributesString = collect($attributes)
            ->map(fn ($value, $key) => "$key=\"$value\"")
            ->implode(' ');

        return "<span class=\"iconify\" data-icon=\"$icon\" $attributesString></span>";
    }
}
