<div>
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $currentPanelId = $this->getCurrentPanelId();
        $panels = $this->getPanels();
        $launchers = $this->getLaunchers();
        $defaultSidebarWidth = (int) config('accelerator.ui.sidebar.default_width', 336);
        $minSidebarWidth = (int) config('accelerator.ui.sidebar.min_width', 288);
        $maxSidebarWidth = (int) config('accelerator.ui.sidebar.max_width', 480);
        $currentTenant = filament()->getTenant();
        $shouldRenderTenantMenu = filament()->hasTenancy()
            && filament()->hasTenantMenu()
            && $currentTenant instanceof \Illuminate\Database\Eloquent\Model;
    @endphp

    <aside
        id="fi-main-sidebar"
        aria-label="{{ __('filament-panels::layout.navigation.label') }}"
        x-cloak
        x-data="{
            width: @js($defaultSidebarWidth),
            minWidth: @js($minSidebarWidth),
            maxWidth: @js($maxSidebarWidth),
            isRtl: @js($isRtl),
            isResizing: false,
            initialize() {
                const storedWidth = Number(localStorage.getItem('accelerator.sidebar.width'))

                if (Number.isFinite(storedWidth) && storedWidth > 0) {
                    this.width = storedWidth
                }

                this.width = this.clamp(this.width)
                this.applyWidth()
            },
            clamp(width) {
                return Math.min(this.maxWidth, Math.max(this.minWidth, Math.round(width)))
            },
            applyWidth() {
                document.documentElement.style.setProperty('--sidebar-width', `${this.width}px`)
            },
            resize(event) {
                if (! this.isResizing || window.innerWidth < 1024) {
                    return
                }

                this.width = this.clamp(this.isRtl ? window.innerWidth - event.clientX : event.clientX)
                this.applyWidth()
            },
            stopResize() {
                if (! this.isResizing) {
                    return
                }

                this.isResizing = false
                document.body.classList.remove('accelerator-sidebar-is-resizing')
                localStorage.setItem('accelerator.sidebar.width', String(this.width))
            },
            resizeBy(amount) {
                this.width = this.clamp(this.width + amount)
                this.applyWidth()
                localStorage.setItem('accelerator.sidebar.width', String(this.width))
            },
        }"
        x-init="initialize()"
        x-bind:class="{ 'fi-sidebar-open': $store.sidebar.isOpen }"
        x-on:pointermove.window="resize($event)"
        x-on:pointerup.window="stopResize()"
        x-on:pointercancel.window="stopResize()"
        class="fi-sidebar fi-main-sidebar accelerator-sidebar"
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_START) }}

        <div class="accelerator-sidebar-body">
            <nav aria-label="Panel" class="accelerator-panel-rail">
                <div class="accelerator-panel-list">
                    @foreach ($panels as $panel)
                        @php
                            $isActive = $currentPanelId === $panel->value;
                        @endphp

                        <a
                            wire:key="accelerator-panel-{{ $panel->value }}"
                            href="{{ $panel->getUrl() }}"
                            aria-label="{{ $panel->getLabel() }}"
                            @if ($isActive) aria-current="page" @endif
                            x-data="{ tooltip: false }"
                            x-effect="
                                tooltip = window.matchMedia('(min-width: 1024px)').matches
                                    ? {
                                          content: @js($panel->getLabel()),
                                          placement: document.dir === 'rtl' ? 'left' : 'right',
                                          theme: $store.theme,
                                      }
                                    : false
                            "
                            x-tooltip.html="tooltip"
                            @class([
                                'accelerator-panel-link',
                                'accelerator-panel-link-active' => $isActive,
                            ])
                        >
                            <x-filament::icon :icon="$panel->getIcon()" class="accelerator-panel-icon" />
                        </a>
                    @endforeach
                </div>

                @if ($launchers !== [])
                    <div class="accelerator-launcher-list">
                        @foreach ($launchers as $launcher)
                            <a
                                wire:key="accelerator-launcher-{{ $launcher->value }}"
                                href="{{ $launcher->getUrl() }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label="{{ $launcher->getLabel() }}"
                                x-data="{ tooltip: false }"
                                x-effect="
                                    tooltip = window.matchMedia('(min-width: 1024px)').matches
                                        ? {
                                              content: @js($launcher->getLabel()),
                                              placement: document.dir === 'rtl' ? 'left' : 'right',
                                              theme: $store.theme,
                                          }
                                        : false
                                "
                                x-tooltip.html="tooltip"
                                class="accelerator-panel-link"
                            >
                                <x-filament::icon :icon="$launcher->getIcon()" class="accelerator-panel-icon" />
                            </a>
                        @endforeach
                    </div>
                @endif

                <x-filament::icon-button
                    color="gray"
                    icon="lucide-x"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-on:click="$store.sidebar.close()"
                    class="accelerator-mobile-rail-close"
                />
            </nav>

            <div
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
                class="accelerator-sidebar-navigation"
            >
                @if ($shouldRenderTenantMenu)
                    <div class="accelerator-sidebar-tenant-menu">
                        <x-filament-panels::tenant-menu />
                    </div>
                @endif

                <nav
                    aria-label="{{ __('filament-panels::layout.navigation.label') }}"
                    class="fi-sidebar-nav accelerator-sidebar-nav"
                >
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

                    <ul class="fi-sidebar-nav-groups">
                        @foreach ($navigation as $group)
                            @php
                                $groupLabel = $group->getLabel();
                            @endphp

                            <x-filament-panels::sidebar.group
                                wire:key="accelerator-navigation-group-{{ md5((string) $groupLabel) }}"
                                :active="$group->isActive()"
                                :collapsible="$group->isCollapsible()"
                                :icon="$group->getIcon()"
                                :items="$group->getItems()"
                                :label="$groupLabel"
                                :sidebar-collapsible="false"
                                :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraSidebarAttributeBag())"
                            />
                        @endforeach
                    </ul>

                    <script>
                        var acceleratorCollapsedGroups = JSON.parse(
                            localStorage.getItem('collapsedGroups'),
                        )

                        if (
                            acceleratorCollapsedGroups === null ||
                            acceleratorCollapsedGroups === 'null'
                        ) {
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
                    </script>

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
                    {{ \Filament\Support\Facades\FilamentView::renderHook('accelerator::sidebar.support') }}
                </nav>
            </div>
        </div>

        <div
            role="separator"
            aria-label="Ubah lebar sidebar"
            aria-orientation="vertical"
            tabindex="0"
            x-show="$store.sidebar.isOpen"
            x-bind:aria-valuemin="minWidth"
            x-bind:aria-valuemax="maxWidth"
            x-bind:aria-valuenow="width"
            x-on:pointerdown.prevent="
                isResizing = true
                document.body.classList.add('accelerator-sidebar-is-resizing')
            "
            x-on:keydown.left.prevent="resizeBy(isRtl ? 16 : -16)"
            x-on:keydown.right.prevent="resizeBy(isRtl ? -16 : 16)"
            class="accelerator-sidebar-resize-handle"
        >
            <span></span>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}
    </aside>
</div>
