<div class="fi-topbar-ctn accelerator-topbar-ctn">
    @php
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $hasNavigation = filament()->hasNavigation();
        $hasTenancy = filament()->hasTenancy();
        $brandLogo = filament()->getBrandLogo();
        $brandName = filament()->getBrandName();
        $homeUrl = filament()->getHomeUrl();
        $user = filament()->auth()->user();
    @endphp

    <nav
        aria-label="{{ __('filament-panels::layout.topbar.label') }}"
        class="fi-topbar accelerator-topbar"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

        <div
            x-data="{}"
            x-bind:style="'--accelerator-brand-width: ' + ($store.sidebar.isOpen ? 'var(--sidebar-width)' : 'var(--collapsed-sidebar-width)')"
            class="accelerator-topbar-brand"
        >
            <a
                @if ($homeUrl)
                    {{ \Filament\Support\generate_href_html($homeUrl) }}
                @endif
                class="accelerator-brand-link"
            >
                <span x-show="$store.sidebar.isOpen" class="accelerator-brand-expanded">
                    <x-filament-panels::logo />

                    @if (filled($brandLogo))
                        <span class="accelerator-brand-name">{{ $brandName }}</span>
                    @endif
                </span>

                <span x-show="! $store.sidebar.isOpen" class="accelerator-brand-compact">
                    <x-filament::icon icon="lucide-zap" class="size-5" />
                </span>
            </a>
        </div>

        <div class="accelerator-topbar-main">
            @if ($hasNavigation)
                <x-filament::icon-button
                    color="gray"
                    icon="lucide-menu"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    aria-controls="fi-main-sidebar"
                    x-bind:aria-expanded="$store.sidebar.isOpen"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="accelerator-mobile-sidebar-open"
                />

                <x-filament::icon-button
                    color="gray"
                    icon="lucide-x"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    aria-controls="fi-main-sidebar"
                    x-bind:aria-expanded="$store.sidebar.isOpen"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="accelerator-mobile-sidebar-close"
                />
            @endif

            @if ($isSidebarCollapsibleOnDesktop)
                <x-filament::icon-button
                    color="gray"
                    :icon="$isRtl ? 'lucide-panel-right-close' : 'lucide-panel-left-close'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    aria-controls="fi-main-sidebar"
                    x-bind:aria-expanded="$store.sidebar.isOpen"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="accelerator-desktop-sidebar-close"
                />

                <x-filament::icon-button
                    color="gray"
                    :icon="$isRtl ? 'lucide-panel-left-open' : 'lucide-panel-right-open'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    aria-controls="fi-main-sidebar"
                    x-bind:aria-expanded="$store.sidebar.isOpen"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="accelerator-desktop-sidebar-open"
                />
            @endif

            <div class="accelerator-topbar-search">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

                @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Topbar)
                    @livewire(Filament\Livewire\GlobalSearch::class)
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}
            </div>

            <div
                @if ($hasTenancy)
                    x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
                @else
                    x-persist="topbar.end.panel-{{ filament()->getId() }}"
                @endif
                class="accelerator-topbar-end"
            >
                @if ($hasTenancy && filament()->hasTenantMenu())
                    <x-filament-panels::tenant-menu teleport />
                @endif

                @if ($user)
                    @if (filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === \Filament\Enums\DatabaseNotificationsPosition::Topbar)
                        @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        ])
                    @endif

                    <div class="accelerator-user-identity">
                        <span>{{ filament()->getUserName($user) }}</span>
                        <small>{{ str(filament()->getCurrentPanel()?->getId())->headline() }}</small>
                    </div>

                    @if (filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Topbar)
                        <x-filament-panels::user-menu />
                    @endif
                @endif
            </div>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
    </nav>

    <x-filament-actions::modals />
</div>
