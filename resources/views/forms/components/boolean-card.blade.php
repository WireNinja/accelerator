@php
    use function Filament\Support\get_color_css_variables;
    
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $icon = $getIcon();
    $label = $getTrueLabel();
    $description = $getTrueDescription();
    $itemColor = 'primary';
    
    $colors = \Illuminate\Support\Arr::toCssStyles([
        get_color_css_variables($itemColor, shades: [50, 100, 400, 500, 600, 700, 800])
    ]);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="fi-fo-boolean-card">
        <label
            for="{{ $getId() }}"
            @class([
                'fi-fo-boolean-card-option group relative flex rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 transition duration-75',
                'hover:border-custom-600 dark:hover:border-custom-500' => !$isDisabled,
                'has-[:checked]:border-custom-600 dark:has-[:checked]:border-custom-500 has-[:checked]:bg-custom-50/30 dark:has-[:checked]:bg-custom-500/5',
                'has-[:checked]:ring-1 has-[:checked]:ring-custom-600 dark:has-[:checked]:ring-custom-500',
                'cursor-pointer' => !$isDisabled,
                'opacity-60 cursor-not-allowed' => $isDisabled,
            ])
            style="{{ $colors }}">
            
            <input 
                type="checkbox"
                {{ $getExtraInputAttributeBag()->merge([
                    'id' => $getId(),
                    'disabled' => $isDisabled,
                    'wire:loading.attr' => 'disabled',
                    $applyStateBindingModifiers('wire:model') => $statePath,
                ], escape: false)->class(['sr-only']) }}
            />

            <div class="flex items-center w-full gap-x-4">
                @if ($icon)
                    <div class="shrink-0 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 p-2.5 group-has-[:checked]:bg-custom-100 dark:group-has-[:checked]:bg-custom-500/20 transition duration-75">
                        @svg($icon, 'size-6 text-gray-500 dark:text-gray-400 group-has-[:checked]:text-custom-600 dark:group-has-[:checked]:text-custom-500')
                    </div>
                @endif

                <div class="fi-fo-boolean-card-text flex-1">
                    <span class="fi-fo-boolean-card-label block text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $label }}
                    </span>
                    @if ($description)
                        <span class="fi-fo-boolean-card-description mt-1 block text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ $description }}
                        </span>
                    @endif
                </div>

                <div class="fi-fo-boolean-card-check flex shrink-0 items-center">
                    <div class="invisible group-has-[:checked]:visible text-custom-600 dark:text-custom-500 transition duration-75">
                        @svg('heroicon-m-check-circle', 'size-6')
                    </div>
                    <div class="visible group-has-[:checked]:invisible text-gray-200 dark:text-gray-800 transition duration-75 absolute">
                        @svg('heroicon-m-check-circle', 'size-6')
                    </div>
                </div>
            </div>
        </label>
    </div>
</x-dynamic-component>
