<div>
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
        $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();
        $hasNavigation = filament()->hasNavigation();
        $hasTopbar = filament()->hasTopbar();
        $currentPanel = $this->getCurrentPanelId();
        $panels = $this->getPanels();
    @endphp

    {{-- format-ignore-start --}}
    <aside
        x-data="{}"
        @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
            x-cloak
        @else
            x-cloak="-lg"
        @endif
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        class="fi-sidebar fi-main-sidebar"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

        <div class="fi-sidebar-header-ctn">
            <header
                class="fi-sidebar-header"
            >
                @if ((! $hasTopbar) && $isSidebarCollapsibleOnDesktop)
                    <x-filament::icon-button
                        color="gray"
                        :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronLeft : \Filament\Support\Icons\Heroicon::OutlinedChevronRight"
                        :icon-alias="
                            $isRtl
                            ? [
                                \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
                                \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON,
                            ]
                            : \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON
                        "
                        icon-size="lg"
                        :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                        x-cloak
                        x-data="{}"
                        x-on:click="$store.sidebar.open()"
                        x-show="! $store.sidebar.isOpen"
                        class="fi-sidebar-open-collapse-sidebar-btn"
                    />
                @endif

                @if ((! $hasTopbar) && ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop))
                    <x-filament::icon-button
                        color="gray"
                        :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronRight : \Filament\Support\Icons\Heroicon::OutlinedChevronLeft"
                        :icon-alias="
                            $isRtl
                            ? [
                                \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
                                \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON,
                            ]
                            : \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON
                        "
                        icon-size="lg"
                        :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                        x-cloak
                        x-data="{}"
                        x-on:click="$store.sidebar.close()"
                        x-show="$store.sidebar.isOpen"
                        class="fi-sidebar-close-collapse-sidebar-btn"
                    />
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_BEFORE) }}

                <div x-show="$store.sidebar.isOpen" class="fi-sidebar-header-logo-ctn">
                    @if ($homeUrl = filament()->getHomeUrl())
                        <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                            <x-filament-panels::logo />
                        </a>
                    @else
                        <x-filament-panels::logo />
                    @endif
                </div>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_AFTER) }}
            </header>
        </div>

        @if (filament()->hasTenancy() && filament()->hasTenantMenu())
            <x-filament-panels::tenant-menu />
        @endif

        @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Sidebar)
            <div
                @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
                    x-show="$store.sidebar.isOpen"
                @endif
            >
                @livewire(Filament\Livewire\GlobalSearch::class)
            </div>
        @endif

        <div @class([
            'flex flex-1 overflow-hidden h-full border-t border-b border-gray-200 dark:border-white/10',
            'flex-row' => !$isRtl,
            'flex-row-reverse' => $isRtl,
        ])>
            <!-- Parent Panels Column (Left/Right depending on RTL) -->
            <div
                x-show="$store.sidebar.isOpen"
                @class([
                    'fi-sidebar-parent flex h-full w-[70px] shrink-0 flex-col py-4 border-gray-200 bg-white/30 dark:border-white/5 dark:bg-gray-900/30',
                    'border-r' => !$isRtl,
                    'border-l' => $isRtl,
                ])
            >
                <div class="flex flex-col gap-y-4 py-4 w-full items-center">
                    @foreach($panels as $panel)
                        @php
                            $isActive = $currentPanel === $panel->value;
                        @endphp

                        <a
                            href="{{ $panel->getUrl() }}"
                            class="group relative flex h-10 w-10 items-center justify-center rounded-xl transition-all duration-300 transform-gpu hover:scale-105 active:scale-95"
                            x-data="{}"
                            x-tooltip.content="'{{ $panel->getLabel() }}'"
                        >
                            <!-- Active Indicator -->
                            <div @class([
                                'absolute h-6 w-1 rounded-full transition-all duration-500',
                                'bg-primary-500 scale-y-100 opacity-100 shadow-[0_0_8px_rgba(var(--primary-500),0.5)]' => $isActive,
                                'bg-gray-400 scale-y-0 opacity-0 group-hover:scale-y-50 group-hover:opacity-50' => !$isActive,
                                '-left-3.5' => !$isRtl,
                                '-right-3.5' => $isRtl,
                            ])></div>

                            <!-- Icon Background Layer -->
                            <div @class([
                                'absolute inset-0 rounded-xl transition-all duration-300',
                                'bg-primary-500/10 dark:bg-primary-500/20 ring-1 ring-primary-500/20' => $isActive,
                                'bg-transparent group-hover:bg-gray-100 dark:group-hover:bg-white/5' => !$isActive,
                            ])></div>

                            <!-- Icon -->
                            <div @class([
                                'relative z-10 transition-colors duration-300',
                                'text-primary-600 dark:text-primary-400' => $isActive,
                                'text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white' => !$isActive,
                            ])>
                                <x-filament::icon
                                    :icon="$panel->getIcon()"
                                    class="h-5 w-5"
                                />
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Main Navigation Column -->
            <nav class="fi-sidebar-nav flex-1 overflow-y-auto">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

                <ul class="fi-sidebar-nav-groups">
                    @foreach ($navigation as $group)
                        @php
                            $isGroupActive = $group->isActive();
                            $isGroupCollapsible = $group->isCollapsible();
                            $groupIcon = $group->getIcon();
                            $groupItems = $group->getItems();
                            $groupLabel = $group->getLabel();
                            $groupExtraSidebarAttributeBag = $group->getExtraSidebarAttributeBag();
                        @endphp

                        <x-filament-panels::sidebar.group
                            :active="$isGroupActive"
                            :collapsible="$isGroupCollapsible"
                            :icon="$groupIcon"
                            :items="$groupItems"
                            :label="$groupLabel"
                            :attributes="\Filament\Support\prepare_inherited_attributes($groupExtraSidebarAttributeBag)"
                        />
                    @endforeach
                </ul>

                <script>
                    var collapsedGroups = JSON.parse(
                        localStorage.getItem('collapsedGroups'),
                    )

                    if (collapsedGroups === null || collapsedGroups === 'null') {
                        localStorage.setItem(
                            'collapsedGroups',
                            JSON.stringify(@js(
                            collect($navigation)
                                ->filter(fn (\Filament\Navigation\NavigationGroup $group): bool => $group->isCollapsed())
                                ->map(fn (\Filament\Navigation\NavigationGroup $group): string => $group->getLabel())
                                ->values()
                                ->all()
                        )),
                        )
                    }

                    collapsedGroups = JSON.parse(
                        localStorage.getItem('collapsedGroups'),
                    )

                    document
                        .querySelectorAll('.fi-sidebar-group')
                        .forEach((group) => {
                            if (
                                !collapsedGroups.includes(group.dataset.groupLabel)
                            ) {
                                return
                            }

                            // Alpine.js loads too slow, so attempt to hide a
                            // collapsed sidebar group earlier.
                            const groupItems = group.querySelector('.fi-sidebar-group-items')
                            if (groupItems) {
                                groupItems.style.display = 'none'
                            }
                            group.classList.add('fi-collapsed')
                        })
                </script>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
            </nav>
        </div>

        @php
            $isAuthenticated = filament()->auth()->check();
            $hasDatabaseNotificationsInSidebar = filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === \Filament\Enums\DatabaseNotificationsPosition::Sidebar;
            $hasUserMenuInSidebar = filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Sidebar;
            $shouldRenderFooter = $isAuthenticated && ($hasDatabaseNotificationsInSidebar || $hasUserMenuInSidebar);
        @endphp

        @if ($shouldRenderFooter)
            <div class="fi-sidebar-footer">
                @if ($hasDatabaseNotificationsInSidebar)
                    @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                        'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                    ])
                @endif

                @if ($hasUserMenuInSidebar)
                    <x-filament-panels::user-menu />
                @endif
            </div>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
    </aside>
    {{-- format-ignore-end --}}

    <x-filament-actions::modals />
</div>
