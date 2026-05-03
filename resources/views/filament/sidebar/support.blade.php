@php
use \WireNinja\Accelerator\Constant\Profile;
@endphp

<div>
    <x-filament::callout
        color="primary">
        <x-slot name="heading">
            Membutuhkan bantuan?
        </x-slot>

        <x-slot name="description">
            Silahkan hubungi tim support kami.
        </x-slot>

        <x-slot name="footer">
            <x-filament::button
                icon="lucide-phone-outgoing"
                size="sm"
                tag="a"
                href="https://wa.me/{{ Profile::DEVELOPER_WHATSAPP }}"
                target="_blank">
                Whatsapp
            </x-filament::button>

            <x-filament::button
                icon="lucide-send"
                size="sm"
                tag="a"
                href="https://t.me/{{ Profile::DEVELOPER_TELEGRAM }}"
                target="_blank">
                Telegram
            </x-filament::button>
        </x-slot>
    </x-filament::callout>
</div>