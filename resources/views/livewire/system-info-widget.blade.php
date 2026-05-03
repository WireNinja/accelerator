<x-filament-widgets::widget class="fi-filament-info-widget">
    <x-filament::section>
        <div class="fi-filament-info-widget-main">
            {{ $this->getAppBrandName() }}
        </div>

        <div class="fi-filament-info-widget-links underline">
            <x-filament::button
                color="primary"
                tag="a"
                href="https://filamentphp.com/docs"
                icon="lucide-phone"
                rel="noopener noreferrer"
                target="_blank">
                Hubungi Support Whatsapp
            </x-filament::button>

            <x-filament::button
                color="gray"
                tag="a"
                href="https://filamentphp.com/docs"
                icon="lucide-ticket"
                rel="noopener noreferrer"
                target="_blank">
                Ajukan Tiket Support
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
