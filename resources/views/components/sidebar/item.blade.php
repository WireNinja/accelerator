@props([
    'active' => false,
    'activeChildItems' => false,
    'activeIcon' => null,
    'badge' => null,
    'badgeColor' => null,
    'badgeTooltip' => null,
    'childItems' => [],
    'first' => false,
    'grouped' => false,
    'icon' => null,
    'last' => false,
    'shouldOpenUrlInNewTab' => false,
    'sidebarCollapsible' => true,
    'subGrouped' => false,
    'subNavigation' => false,
    'url',
])

@php
    $sidebarCollapsible = $sidebarCollapsible && filament()->isSidebarCollapsibleOnDesktop();
@endphp

<li
    {{
        $attributes->class([
            'fi-sidebar-item group',
            'fi-active' => $active,
            'fi-sidebar-item-has-active-child-items' => $activeChildItems,
            'fi-sidebar-item-has-url' => filled($url),
        ])
    }}
>
    <a
        {{ \Filament\Support\generate_href_html($url, $shouldOpenUrlInNewTab) }}
        x-on:click="window.matchMedia(`(max-width: 1024px)`).matches && $store.sidebar.close()"
        @if ($sidebarCollapsible && (! $subNavigation))
            x-data="{ tooltip: false }"
            x-effect="
                tooltip = $store.sidebar.isOpen
                    ? false
                    : {
                          content: @js($slot->toHtml()),
                          placement: document.dir === 'rtl' ? 'left' : 'right',
                          theme: $store.theme,
                      }
            "
            x-tooltip.html="tooltip"
        @endif
        @class([
            'flex items-center gap-x-3 px-3 py-2 text-sm font-medium transition-all duration-200 rounded-xl',
            'text-primary-600 bg-primary-50 dark:text-primary-400 dark:bg-primary-400/10 shadow-sm shadow-primary-500/10' => $active,
            'text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' => !$active,
        ])
    >
        @if (filled($icon) && ((! $subGrouped) || ($sidebarCollapsible && (! $subNavigation))))
            <div @class([
                'shrink-0 transition-transform duration-200 group-hover:scale-110',
                'text-primary-600 dark:text-primary-400' => $active,
                'text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300' => !$active,
            ])>
                {{
                    \Filament\Support\generate_icon_html(($active && $activeIcon) ? $activeIcon : $icon, attributes: (new \Illuminate\View\ComponentAttributeBag([
                        'x-show' => ($subGrouped && $sidebarCollapsible) ? '! $store.sidebar.isOpen' : false,
                    ]))->class(['h-5 w-5']), size: \Filament\Support\Enums\IconSize::Medium)
                }}
            </div>
        @endif

        @if ((blank($icon) && $grouped) || $subGrouped)
            <div
                @if (filled($icon) && $subGrouped && $sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-sidebar-item-grouped-border relative w-5 flex items-center justify-center"
            >
                <div @class([
                    'h-1.5 w-1.5 rounded-full transition-all duration-200',
                    'bg-primary-500 ring-4 ring-primary-500/20 shadow-[0_0_8px_rgba(var(--primary-500),0.4)]' => $active,
                    'bg-gray-300 group-hover:bg-gray-400 dark:bg-gray-700 dark:group-hover:bg-gray-600' => !$active,
                ])></div>
            </div>
        @endif

        <span
            @if ($sidebarCollapsible && (! $subNavigation))
                x-show="$store.sidebar.isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-2"
                x-transition:enter-end="opacity-100 translate-x-0"
            @endif
            class="flex-1 truncate"
        >
            {{ $slot }}
        </span>

        @if (filled($badge))
            <span
                @if ($sidebarCollapsible && (! $subNavigation))
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-90"
                    x-transition:enter-end="opacity-100 scale-100"
                @endif
                class="shrink-0"
            >
                <x-filament::badge
                    :color="$badgeColor"
                    :tooltip="$badgeTooltip"
                    size="sm"
                >
                    {{ $badge }}
                </x-filament::badge>
            </span>
        @endif
    </a>

    @if (($active || $activeChildItems) && $childItems)
        <ul class="mt-1 flex flex-col gap-y-1">
            @foreach ($childItems as $childItem)
                @php
                    $isChildItemChildItemsActive = $childItem->isChildItemsActive();
                    $isChildActive = (! $isChildItemChildItemsActive) && $childItem->isActive();
                    $childItemActiveIcon = $childItem->getActiveIcon();
                    $childItemBadge = $childItem->getBadge();
                    $childItemBadgeColor = $childItem->getBadgeColor($childItemBadge);
                    $childItemBadgeTooltip = $childItem->getBadgeTooltip($childItemBadge);
                    $childItemIcon = $childItem->getIcon();
                    $shouldChildItemOpenUrlInNewTab = $childItem->shouldOpenUrlInNewTab();
                    $childItemUrl = $childItem->getUrl();
                    $childItemExtraAttributes = $childItem->getExtraAttributeBag();
                @endphp

                <x-accelerator::sidebar.item
                    :active="$isChildActive"
                    :active-child-items="$isChildItemChildItemsActive"
                    :active-icon="$childItemActiveIcon"
                    :badge="$childItemBadge"
                    :badge-color="$childItemBadgeColor"
                    :badge-tooltip="$childItemBadgeTooltip"
                    :first="$loop->first"
                    grouped
                    :icon="$childItemIcon"
                    :last="$loop->last"
                    :should-open-url-in-new-tab="$shouldChildItemOpenUrlInNewTab"
                    sub-grouped
                    :sub-navigation="$subNavigation"
                    :url="$childItemUrl"
                    :attributes="\Filament\Support\prepare_inherited_attributes($childItemExtraAttributes)"
                >
                    {{ $childItem->getLabel() }}
                </x-accelerator::sidebar.item>
            @endforeach
        </ul>
    @endif
</li>
