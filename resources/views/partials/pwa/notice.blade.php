@php
    $notice = rescue(fn() => resolve(\WireNinja\Accelerator\Settings\SystemSettings::class)->app_notice);
@endphp

@if(filled($notice))
<div class="px-2">
    <x-filament::callout
        icon="lucide-megaphone"
        color="warning"
    >
        <x-slot name="heading">
            {{ $notice }}
        </x-slot>
    </x-filament::callout>
</div>
@endif
