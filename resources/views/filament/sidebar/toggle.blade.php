<div 
    class="fi-sidebar-toggle-hook mt-6 ml-2" 
    x-cloak 
    x-data="{}" 
    x-show="! $store.sidebar.isOpen"
>
    <x-filament::icon-button
        icon="heroicon-o-bars-3"
        color="gray"
        size="lg"
        label="Open Sidebar"
        x-on:click="$store.sidebar.open()"
        class="bg-white/50 dark:bg-white/5 backdrop-blur-sm border border-gray-200/50 dark:border-white/10 shadow-sm hover:bg-white dark:hover:bg-white/10 rounded-xl transition-all"
    />
</div>
