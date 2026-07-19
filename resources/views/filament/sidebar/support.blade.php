@php
$supportEnabled = resolve(\WireNinja\Accelerator\Settings\SystemSettings::class)->support_enabled;
$whatsapp = preg_replace('/\D+/', '', (string) config('accelerator.support.whatsapp'));
$telegram = ltrim((string) config('accelerator.support.telegram'), '@');
@endphp

@if($supportEnabled && (filled($whatsapp) || filled($telegram)))
<div>
    <x-filament::callout
        color="primary">
        <x-slot name="heading">
            Membutuhkan bantuan?
        </x-slot>

        <x-slot name="description">
            Silahkan hubungi tim support.
        </x-slot>

        <x-slot name="footer">
            @if(filled($whatsapp))
            <x-filament::button
                icon="lucide-phone-outgoing"
                size="sm"
                tag="a"
                href="https://wa.me/{{ $whatsapp }}"
                target="_blank"
                class="w-full">
                Whatsapp
            </x-filament::button>
            @endif

            @if(filled($telegram))
            <x-filament::button
                icon="lucide-send"
                size="sm"
                tag="a"
                href="https://t.me/{{ $telegram }}"
                target="_blank"
                class="w-full">
                Telegram
            </x-filament::button>
            @endif
        </x-slot>
    </x-filament::callout>
</div>
@endif
