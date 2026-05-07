<x-filament-widgets::widget>
    <div class="flex flex-col gap-y-4">
        <div class="flex flex-col gap-y-1">
            <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Quick Access
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Akses cepat ke aplikasi pendukung eksternal
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            @foreach ($this->getLaunchers() as $launcher)
                <a
                    href="{{ $launcher->getUrl() }}"
                    target="_blank"
                    class="group relative flex flex-col items-center justify-center gap-y-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 transition-all duration-300 hover:bg-gray-50 hover:shadow-md dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
                >
                    <div @class([
                        'flex h-12 w-12 items-center justify-center rounded-xl transition-all duration-300 group-hover:scale-110',
                        'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400',
                    ])>
                        <x-filament::icon
                            :icon="$launcher->getIcon()"
                            class="h-6 w-6"
                        />
                    </div>

                    <span class="text-sm font-medium text-gray-700 transition-colors duration-300 group-hover:text-gray-950 dark:text-gray-300 dark:group-hover:text-white">
                        {{ $launcher->getLabel() }}
                    </span>

                    {{-- Subtle External Indicator --}}
                    <div class="absolute right-3 top-3 opacity-0 transition-opacity duration-300 group-hover:opacity-100">
                        <x-filament::icon
                            icon="lucide-external-link"
                            class="h-3 w-3 text-gray-400"
                        />
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
