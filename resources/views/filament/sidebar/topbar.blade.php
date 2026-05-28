<div
    class="fi-sidebar-topbar-hook"
    x-cloak
    x-data="{}"
>
    <div class="flex min-h-16 items-center justify-between gap-3 rounded-xl bg-white px-3 py-2 shadow-sm ring-1 ring-gray-950/5 transition-all dark:bg-gray-900 dark:ring-white/10">
        <div class="flex min-w-0 items-center gap-2 ml-2">
            <x-filament::icon-button
                icon="heroicon-o-bars-3"
                color="gray"
                size="lg"
                label="Buka Sidebar"
                x-show="! $store.sidebar.isOpen"
                x-on:click="$store.sidebar.open()"
                class="shrink-0 rounded-xl bg-gray-200/50"
            />
        </div>

        <div class="flex shrink-0 items-center gap-2">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
        </div>
    </div>
</div>
