@php
    use function Filament\Support\get_color_css_variables;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Contracts\HasIcon;

    $descriptions = $getDescriptions();
    $extras = $getExtras();
    $hiddenInputs = $getHiddenInputs();
    $columns = $getColumns();
    $gridDirection = $getGridDirection();
    $isInline = false;
    $enum = $getEnum();
    $isDisabled = $isDisabled();
    $hasCursorPointer = $hasCursorPointer();
    $isSearchable = $isSearchable();
    $isBulkToggleable = $isBulkToggleable();
    $statePath = $getStatePath();
    $options = $getOptions();
    $livewireKey = $getLivewireKey();
    $extraInputAttributeBag = $getExtraInputAttributeBag();
    $wireModelAttribute = $applyStateBindingModifiers('wire:model');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div @if (FilamentView::hasSpaMode()) {{-- format-ignore-start --}}x-load="visible || event (x-modal-opened)" {{--
    format-ignore-end --}} @else x-load @endif
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('checkbox-list', 'filament/forms') }}"
        x-data="checkboxListFormComponent({
                    livewireId: @js($this->getId()),
                })" class="fi-fo-checkbox-list space-y-4">

        @if (!$isDisabled && ($isSearchable || $isBulkToggleable))
            <div class="flex items-center gap-x-3">
                @if ($isSearchable)
                    <x-filament::input.wrapper inline-prefix :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass"
                        prefix-icon-alias="forms:components.checkbox-list.search-field"
                        class="flex-1">
                        <input placeholder="{{ $getSearchPrompt() }}" type="search" x-model="search"
                            class="fi-input fi-input-has-inline-prefix" />
                    </x-filament::input.wrapper>
                @endif

                @if ($isBulkToggleable)
                    <div class="flex shrink-0 gap-x-2">
                        <x-filament::link
                            color="gray"
                            tag="button"
                            size="sm"
                            x-on:click="checkAll()"
                        >
                            {{ __('filament-forms::components.checkbox_list.actions.deselect_all.label') }}
                        </x-filament::link>

                        <x-filament::link
                            color="gray"
                            tag="button"
                            size="sm"
                            x-on:click="uncheckAll()"
                        >
                            {{ __('filament-forms::components.checkbox_list.actions.deselect_all.label') }}
                        </x-filament::link>
                    </div>
                @endif
            </div>
        @endif

        <fieldset {{
            $getExtraAttributeBag()
                ->when(!$isInline, fn($attributes) => $attributes->grid($columns, $gridDirection))
                ->merge([
                    'x-show' => $isSearchable ? 'visibleCheckboxListOptions.length' : null,
                ], escape: false)
                ->class([
                    'fi-fo-checkbox-list-options',
                    'gap-4',
                ])
            }}>
            @foreach($options as $value => $label)
                @php
                    $id = str_replace('.', '-', $statePath) . '-' . $value;
                    $description = $descriptions[$value] ?? null;
                    $extra = $extras[$value] ?? null;
                    $icon = null;
                    $itemColor = 'primary';

                    if ($enum) {
                        $case = $getEnum()::tryFrom($value) ?: null;

                        if ($case) {
                            if (method_exists($case, 'getColor') && $color = $case->getColor()) {
                                $itemColor = $color;
                            }

                            if ($case instanceof HasIcon) {
                                $icon = $case->getIcon();
                            }
                        }
                    }

                    $colors = \Illuminate\Support\Arr::toCssStyles([
                        get_color_css_variables($itemColor, shades: [50, 100, 400, 500, 600, 700, 800])
                    ]);
                @endphp

                <div @if ($isSearchable) wire:key="{{ $livewireKey }}.options.{{ $value }}" x-show="
                    $el
                        .querySelector('.fi-fo-checkbox-list-option-label')
                        ?.innerText.toLowerCase()
                        .includes(search.toLowerCase()) ||
                        $el
                            .querySelector('.fi-fo-checkbox-list-option-description')
                            ?.innerText.toLowerCase()
                            .includes(search.toLowerCase())
                " @endif class="fi-fo-checkbox-list-option-ctn">
                    <label for="{{ $id }}"
                        @class([
                            'fi-fo-checkbox-list-option group relative flex rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 transition duration-75',
                            'hover:border-custom-600 dark:hover:border-custom-500' => !$isDisabled,
                            'has-checked:border-custom-600 dark:has-checked:border-custom-500 has-checked:bg-custom-50/30 dark:has-checked:bg-custom-500/5',
                            'has-checked:ring-1 has-checked:ring-custom-600 dark:has-checked:ring-custom-500',
                            'not-has-disabled:cursor-pointer' => $hasCursorPointer,
                            'opacity-60 cursor-not-allowed' => $isDisabled,
                        ])
                        style="{{ $colors }}">
                        
                        @if($hiddenInputs)
                            <input id="{{ $id }}" name="{{ $statePath }}" type="checkbox" value="{{ $value }}" {{
                                $getExtraInputAttributeBag()
                                    ->merge([
                                         'disabled' => $isDisabled || $isOptionDisabled($value, $label),
                                        'wire:loading.attr' => 'disabled',
                                        $wireModelAttribute => $statePath,
                                    ], escape: false)
                                    ->class([
                                        'absolute inset-0 appearance-none focus:outline-none',
                                    ])
                            }} />
                        @endif

                        <div class="flex items-center w-full gap-x-4">
                            @if ($icon)
                                <div class="shrink-0 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 p-2.5 group-has-checked:bg-custom-100 dark:group-has-checked:bg-custom-500/20 transition duration-75">
                                    @svg($icon, 'size-6 text-gray-500 dark:text-gray-400 group-has-checked:text-custom-600 dark:group-has-checked:text-custom-500')
                                </div>
                            @endif

                            <div class="fi-fo-checkbox-list-option-text flex-1">
                                <span class="fi-fo-checkbox-list-option-label block text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $label }}
                                </span>
                                @if ($description)
                                    <span class="fi-fo-checkbox-list-option-description mt-1 block text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                        {{ $description }}
                                    </span>
                                @endif
                                @if ($extra)
                                    <span class="fi-fo-checkbox-list-option-extra mt-2 block text-xs font-medium text-custom-600 dark:text-custom-500">
                                        {{ $extra }}
                                    </span>
                                @endif
                            </div>

                            @if(!$hiddenInputs)
                                <input id="{{ $id }}" name="{{ $statePath }}" type="checkbox" value="{{ $value }}" {{
                                    $getExtraInputAttributeBag()
                                        ->merge([
                                            'disabled' => $isDisabled || $isOptionDisabled($value, $label),
                                            'wire:loading.attr' => 'disabled',
                                            $wireModelAttribute => $statePath,
                                        ], escape: false)
                                        ->class([
                                            'fi-checkbox-input size-5 rounded border-gray-300 dark:border-gray-700 text-custom-600 focus:ring-custom-500 dark:bg-gray-900',
                                            'fi-valid' => !$errors->has($statePath),
                                            'fi-invalid' => $errors->has($statePath),
                                        ])
                                }} style="{{ $colors }}" />
                            @endif
                        </div>

                        @if($hiddenInputs)
                            <div class="invisible group-has-checked:visible absolute top-3 right-3 text-custom-600 dark:text-custom-500">
                                @svg($getHiddenInputIcon() ?? 'heroicon-m-check-circle', 'size-5')
                            </div>
                        @endif
                    </label>
                </div>
            @endforeach
        </fieldset>

        @if ($isSearchable)
            <div x-cloak x-show="search && ! visibleCheckboxListOptions.length"
                class="fi-fo-checkbox-list-no-search-results-message text-center py-4 text-sm text-gray-500 dark:text-gray-400">
                {{ $getNoSearchResultsMessage() }}
            </div>
        @endif
    </div>
</x-dynamic-component>
