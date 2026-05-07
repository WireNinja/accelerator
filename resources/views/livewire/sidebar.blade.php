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

        $isAuthenticated = filament()->auth()->check();
        $hasDatabaseNotificationsInSidebar = filament()->hasDatabaseNotifications() && filament()->getDatabaseNotificationsPosition() === \Filament\Enums\DatabaseNotificationsPosition::Sidebar;
        $hasUserMenuInSidebar = filament()->hasUserMenu() && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Sidebar;
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
        @class([
            'fi-sidebar fi-main-sidebar',
            'flex flex-col h-screen h-[100dvh] overflow-hidden bg-transparent border-none shadow-none rounded-2xl transition-all duration-300 p-0',
        ])
        x-bind:style="$store.sidebar.isOpen ? 'width: {{ filament()->getSidebarWidth() }}' : 'width: {{ filament()->getCollapsedSidebarWidth() }}'"
    >
        <div class="flex-1 mx-2.5 mt-2 mb-[calc(0.5rem+env(safe-area-inset-bottom))] lg:mb-2 flex flex-col bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl shadow-2xl shadow-gray-200/50 dark:shadow-none rounded-2xl border border-gray-200/50 dark:border-white/10 transition-all duration-300 overflow-hidden">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

            <div class="fi-sidebar-header-ctn shrink-0 border-b border-gray-100 dark:border-white/5">
                <header
                    class="fi-sidebar-header flex items-center justify-between px-2 pt-2 pb-2"
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
                            x-data='{}'
                            x-on:click="$store.sidebar.open()"
                            x-show="! $store.sidebar.isOpen"
                            class="hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition-colors"
                        />
                    @endif

                    <div x-show="$store.sidebar.isOpen" class="flex-1 px-1">
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_BEFORE) }}
                        <div class="fi-sidebar-header-logo-ctn transition-all duration-300 transform">
                            @if ($homeUrl = filament()->getHomeUrl())
                                <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="block focus:outline-none">
                                    <x-filament-panels::logo />
                                </a>
                            @else
                                <x-filament-panels::logo />
                            @endif
                        </div>
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_LOGO_AFTER) }}
                    </div>

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
                            x-data='{}'
                            x-on:click="$store.sidebar.close()"
                            x-show="$store.sidebar.isOpen"
                            class="hover:bg-gray-100 dark:hover:bg-white/5 rounded-xl transition-colors mr-1"
                        />
                    @endif
                </header>
            </div>

            @if (filament()->hasTenancy() && filament()->hasTenantMenu() && filament()->getTenantMenuPosition() === \Filament\Enums\TenantMenuPosition::Sidebar)
                <div class="px-2 py-2 border-b border-gray-100 dark:border-white/5">
                    <x-filament-panels::tenant-menu />
                </div>
            @endif

            @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Sidebar)
                <div
                    class="px-2 py-2 border-b border-gray-100 dark:border-white/5"
                    @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
                        x-show="$store.sidebar.isOpen"
                    @endif
                >
                    @livewire(Filament\Livewire\GlobalSearch::class)
                </div>
            @endif

            <div @class([
                'flex flex-1 overflow-hidden h-full',
                'flex-row' => !$isRtl,
                'flex-row-reverse' => $isRtl,
            ])>
                <!-- Parent Panels Column -->
                <div
                    x-show="$store.sidebar.isOpen"
                    @class([
                        'fi-sidebar-parent flex h-full w-[66px] shrink-0 flex-col py-2 justify-between',
                        'border-r border-gray-100 dark:border-white/5' => !$isRtl,
                        'border-l border-gray-100 dark:border-white/5' => $isRtl,
                    ])
                >
                    <div class="flex flex-col gap-y-3 py-2 w-full items-center">
                        @foreach($panels as $panel)
                            @php
                                $isActive = $currentPanel === $panel->value;
                            @endphp

                            <a
                                href="{{ $panel->getUrl() }}"
                                class="group relative flex h-9 w-9 items-center justify-center rounded-xl transition-all duration-300 transform-gpu hover:scale-110 active:scale-95"
                                x-data="{}"
                                x-tooltip.content="'{{ $panel->getLabel() }}'"
                            >
                                <!-- Active Indicator -->
                                <div @class([
                                    'absolute h-5 w-1 rounded-full transition-all duration-500',
                                    'bg-primary-500 scale-y-100 opacity-100 shadow-[0_0_10px_rgba(var(--primary-500),0.6)]' => $isActive,
                                    'bg-gray-400 scale-y-0 opacity-0 group-hover:scale-y-50 group-hover:opacity-50' => !$isActive,
                                    '-left-1' => !$isRtl,
                                    '-right-1' => $isRtl,
                                ])></div>

                                <!-- Icon Background Layer -->
                                <div @class([
                                    'absolute inset-0 rounded-xl transition-all duration-300',
                                    'bg-primary-500/10 dark:bg-primary-500/20 ring-1 ring-primary-500/20' => $isActive,
                                    'bg-transparent group-hover:bg-gray-200 dark:group-hover:bg-white/10' => !$isActive,
                                ])></div>

                                <!-- Icon -->
                                <div @class([
                                    'relative z-10 transition-colors duration-300',
                                    'text-primary-600 dark:text-primary-400' => $isActive,
                                    'text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white' => !$isActive,
                                ])>
                                    <x-filament::icon
                                        :icon="$panel->getIcon()"
                                        class="h-4.5 w-4.5"
                                    />
                                </div>
                            </a>
                        @endforeach

                        @php
                            $launchers = $this->getLaunchers();
                        @endphp

                        @if (count($launchers) > 0)
                            <div class="h-px w-10 bg-gray-200 dark:bg-white/10 my-2"></div>

                            @foreach ($launchers as $launcher)
                                <a
                                    href="{{ $launcher->getUrl() }}"
                                    target="_blank"
                                    class="group relative flex h-9 w-9 items-center justify-center rounded-xl transition-all duration-300 transform-gpu hover:scale-110 active:scale-95"
                                    x-data="{}"
                                    x-tooltip.content="'{{ $launcher->getLabel() }}'"
                                >
                                    <!-- Icon Background Layer -->
                                    <div class="absolute inset-0 rounded-xl transition-all duration-300 bg-transparent group-hover:bg-gray-200 dark:group-hover:bg-white/10"></div>

                                    <!-- Icon -->
                                    <div class="relative z-10 transition-colors duration-300 text-gray-500 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white">
                                        <x-filament::icon
                                            :icon="$launcher->getIcon()"
                                            class="h-4.5 w-4.5"
                                        />
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>

                    @if ($hasDatabaseNotificationsInSidebar)
                        <div class="flex flex-col items-center pb-2">
                            <div class="fi-sidebar-parent-notifications">
                                @livewire(filament()->getDatabaseNotificationsLivewireComponent(), [
                                    'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                                ])
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Main Navigation Column -->
                <nav class="fi-sidebar-nav flex-1 overflow-y-auto overflow-x-hidden custom-scrollbar pt-4 pb-2">
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

                    <ul class="fi-sidebar-nav-groups flex flex-col gap-y-1 px-0">
                        @foreach ($navigation as $group)
                            @php
                                $isGroupActive = $group->isActive();
                                $isGroupCollapsible = $group->isCollapsible();
                                $groupIcon = $group->getIcon();
                                $groupItems = $group->getItems();
                                $groupLabel = $group->getLabel();
                                $groupExtraSidebarAttributeBag = $group->getExtraSidebarAttributeBag();
                            @endphp

                            <x-accelerator::sidebar.group
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
                $shouldRenderFooter = $isAuthenticated && $hasUserMenuInSidebar;
            @endphp

            @if ($shouldRenderFooter)
                <div class="fi-sidebar-footer shrink-0 px-2 pt-3 pb-3 lg:pb-2 border-t border-gray-100 dark:border-white/5">
                    <div class="flex flex-col gap-y-1">

                        @if ($hasUserMenuInSidebar)
                            <div class="w-full">
                                <x-filament-panels::user-menu />
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
        </div>
    </aside>

    {{-- format-ignore-end --}}

    <x-filament-actions::modals />

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.05);
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.1);
        }
        .fi-sidebar-notifications-ctn button,
        .fi-sidebar-notifications-ctn .fi-icon-btn {
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            border-radius: 0.75rem !important;
        }
        .fi-sidebar-notifications-ctn button span,
        .fi-sidebar-notifications-ctn .fi-icon-btn span {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 0.5rem !important;
            width: 100% !important;
        }

        .fi-sidebar-parent-notifications button {
            width: 36px !important;
            height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 0.75rem !important;
            transition: all 0.3s !important;
            position: relative !important;
            background-color: transparent !important;
            color: var(--gray-500) !important;
        }
        .fi-sidebar-parent-notifications button:hover {
            background-color: rgba(0, 0, 0, 0.05) !important;
            color: var(--gray-900) !important;
        }
        .dark .fi-sidebar-parent-notifications button:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }
        .fi-sidebar-parent-notifications .fi-sidebar-database-notifications-btn-label {
            display: none !important;
        }
        .fi-sidebar-parent-notifications .fi-sidebar-database-notifications-btn-badge-ctn {
            position: absolute !important;
            top: -2px !important;
            right: -2px !important;
            z-index: 10 !important;
        }
        .fi-sidebar-parent-notifications .fi-sidebar-database-notifications-btn-badge-ctn .fi-badge {
            padding: 0 !important;
            min-width: 16px !important;
            height: 16px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 10px !important;
            border: 2px solid white !important;
        }
        .dark .fi-sidebar-parent-notifications .fi-sidebar-database-notifications-btn-badge-ctn .fi-badge {
            border-color: #111827 !important;
        }
    </style>
</div>