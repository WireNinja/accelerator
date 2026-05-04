@props([
    'active' => false,
    'collapsible' => true,
    'icon' => null,
    'items' => [],
    'label' => null,
    'sidebarCollapsible' => true,
    'subNavigation' => false,
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
    $hasDropdown = filled($label) && filled($icon) && $sidebarCollapsible;
@endphp

<li
    x-data="{ label: @js($subNavigation ? "sub_navigation_{$label}" : $label) }"
    data-group-label="{{ $subNavigation ? "sub_navigation_{$label}" : $label }}"
    x-bind:class="{ 'fi-collapsed': $store.sidebar.groupIsCollapsed(label) }"
    {{
        $attributes->class([
            'fi-sidebar-group flex flex-col gap-y-1',
            'fi-active' => $active,
            'fi-collapsible' => $collapsible,
        ])
    }}
>
    @if ($label)
        <div
            @if ($collapsible)
                x-on:click="$store.sidebar.toggleCollapsedGroup(label)"
            @endif
            @if ($sidebarCollapsible)
                x-show="$store.sidebar.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
            @endif
            class="@if ($collapsible) cursor-pointer @endif flex items-center gap-x-3 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 transition-colors duration-200 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            @if ($icon)
                {{ \Filament\Support\generate_icon_html($icon, size: \Filament\Support\Enums\IconSize::Medium, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'shrink-0'])) }}
            @endif

            <span class="flex-1 truncate text-[10px]">
                {{ $label }}
            </span>

            @if ($collapsible)
                <x-filament::icon-button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::ChevronUp"
                    :icon-alias="\Filament\View\PanelsIconAlias::SIDEBAR_GROUP_COLLAPSE_BUTTON"
                    :label="$label"
                    x-bind:aria-expanded="! $store.sidebar.groupIsCollapsed(label)"
                    x-on:click.stop="$store.sidebar.toggleCollapsedGroup(label)"
                    class="fi-sidebar-group-collapse-btn transition-transform duration-200"
                    x-bind:class="{ 'rotate-180': $store.sidebar.groupIsCollapsed(label) }"
                />
            @endif
        </div>
    @endif

    @if ($hasDropdown)
        <x-filament::dropdown
            :placement="(__('filament-panels::layout.direction') === 'rtl') ? 'left-start' : 'right-start'"
            x-show="! $store.sidebar.isOpen"
        >
            <x-slot name="trigger">
                <button
                    x-data="{ tooltip: false }"
                    x-effect="
                        tooltip = $store.sidebar.isOpen
                            ? false
                            : {
                                  content: @js($label),
                                  placement: document.dir === 'rtl' ? 'left' : 'right',
                                  theme: $store.theme,
                              }
                    "
                    x-tooltip.html="tooltip"
                    class="flex h-10 w-10 items-center justify-center rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-white/5"
                >
                    {{ \Filament\Support\generate_icon_html($icon, size: \Filament\Support\Enums\IconSize::Large) }}
                </button>
            </x-slot>

            @php
                $lists = [];

                foreach ($items as $item) {
                    if ($childItems = $item->getChildItems()) {
                        $lists[] = [
                            $item,
                            ...$childItems,
                        ];
                        $lists[] = [];

                        continue;
                    }

                    if (empty($lists)) {
                        $lists[] = [$item];

                        continue;
                    }

                    $lists[count($lists) - 1][] = $item;
                }

                if (empty($lists[count($lists) - 1])) {
                    array_pop($lists);
                }
            @endphp

            @if (filled($label))
                <x-filament::dropdown.header>
                    {{ $label }}
                </x-filament::dropdown.header>
            @endif

            @foreach ($lists as $list)
                <x-filament::dropdown.list>
                    @foreach ($list as $item)
                        @php
                            $itemIsActive = $item->isActive();
                            $itemBadge = $item->getBadge();
                            $itemBadgeColor = $item->getBadgeColor($itemBadge);
                            $itemBadgeTooltip = $item->getBadgeTooltip($itemBadge);
                            $itemUrl = $item->getUrl();
                            $itemIcon = $itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon();
                            $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                            $itemExtraAttributes = $item->getExtraAttributeBag();
                        @endphp

                        <x-filament::dropdown.list.item
                            :badge="$itemBadge"
                            :badge-color="$itemBadgeColor"
                            :badge-tooltip="$itemBadgeTooltip"
                            :color="$itemIsActive ? 'primary' : 'gray'"
                            :href="$itemUrl"
                            :icon="$itemIcon"
                            tag="a"
                            :target="$shouldItemOpenUrlInNewTab ? '_blank' : null"
                            :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraAttributes)"
                        >
                            {{ $item->getLabel() }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            @endforeach
        </x-filament::dropdown>
    @endif

    <ul
        @if (filled($label))
            @if ($sidebarCollapsible)
                x-show="$store.sidebar.isOpen ? ! $store.sidebar.groupIsCollapsed(label) : ! @js($hasDropdown)"
            @else
                x-show="! $store.sidebar.groupIsCollapsed(label)"
            @endif
            x-collapse.duration.250ms
        @endif
        class="fi-sidebar-group-items flex flex-col gap-y-1"
    >
        @foreach ($items as $item)
            @php
                $isItemChildItemsActive = $item->isChildItemsActive();
                $isItemActive = (! $isItemChildItemsActive) && $item->isActive();
                $itemActiveIcon = $item->getActiveIcon();
                $itemBadge = $item->getBadge();
                $itemBadgeColor = $item->getBadgeColor($itemBadge);
                $itemBadgeTooltip = $item->getBadgeTooltip($itemBadge);
                $itemChildItems = $item->getChildItems();
                $itemIcon = $item->getIcon();
                $shouldItemOpenUrlInNewTab = $item->shouldOpenUrlInNewTab();
                $itemUrl = $item->getUrl();
                $itemExtraAttributes = $item->getExtraAttributeBag();

                if ($icon) {
                    if ($hasDropdown || (blank($itemIcon) && blank($itemActiveIcon))) {
                        $itemIcon = null;
                        $itemActiveIcon = null;
                    } else {
                        throw new \Exception('Navigation group [' . $label . '] has an icon but one or more of its items also have icons. Either the group or its items can have icons, but not both. This is to ensure a proper user experience.');
                    }
                }
            @endphp

            <x-accelerator::sidebar.item
                :active="$isItemActive"
                :active-child-items="$isItemChildItemsActive"
                :active-icon="$itemActiveIcon"
                :badge="$itemBadge"
                :badge-color="$itemBadgeColor"
                :badge-tooltip="$itemBadgeTooltip"
                :child-items="$itemChildItems"
                :first="$loop->first"
                :grouped="filled($label)"
                :icon="$itemIcon"
                :last="$loop->last"
                :should-open-url-in-new-tab="$shouldItemOpenUrlInNewTab"
                :sidebar-collapsible="$sidebarCollapsible"
                :sub-navigation="$subNavigation"
                :url="$itemUrl"
                :attributes="\Filament\Support\prepare_inherited_attributes($itemExtraAttributes)"
            >
                {{ $item->getLabel() }}

                @if ($itemIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                    <x-slot name="icon">
                        {{ $itemIcon }}
                    </x-slot>
                @endif

                @if ($itemActiveIcon instanceof \Illuminate\Contracts\Support\Htmlable)
                    <x-slot name="activeIcon">
                        {{ $itemActiveIcon }}
                    </x-slot>
                @endif
            </x-accelerator::sidebar.item>
        @endforeach
    </ul>
</li>
