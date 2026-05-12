@php
    $isContained = $isContained();
    $key = $getKey();
    $previousAction = $getAction('previous');
    $nextAction = $getAction('next');
    $steps = $getChildSchema()->getComponents();
    $isHeaderHidden = $isHeaderHidden();
    $isSticky = $isSticky();
@endphp

<style>
    /* Ensure inactive steps are hidden completely to prevent the "all forms showing" bug */
    .fi-sc-vertical-wizard .fi-sc-wizard-step:not(.fi-active) {
        display: none !important;
    }
</style>

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('wizard', 'filament/schemas') }}"
    x-data="wizardSchemaComponent({
                isSkippable: @js($isSkippable()),
                isStepPersistedInQueryString: @js($isStepPersistedInQueryString()),
                key: @js($key),
                startStep: @js($getStartStep()),
                stepQueryStringKey: @js($getStepQueryStringKey()),
            })"
    x-on:next-wizard-step.window="if ($event.detail.key === @js($key)) goToNextStep()"
    x-on:go-to-wizard-step.window="$event.detail.key === @js($key) && goToStep($event.detail.step)"
    wire:ignore.self
    {{
        $attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
            ->class([
                'fi-sc-vertical-wizard w-full',
                'fi-contained' => $isContained,
                'fi-sc-vertical-wizard-header-hidden' => $isHeaderHidden,
            ])
    }}
>
    <input
        type="hidden"
        value="{{
            collect($steps)
                ->filter(static fn (\Filament\Schemas\Components\Wizard\Step $step): bool => $step->isVisible())
                ->map(static fn (\Filament\Schemas\Components\Wizard\Step $step): ?string => $step->getKey())
                ->values()
                ->toJson()
        }}"
        x-ref="stepsData"
    />

    <!-- Main Wizard Canvas -->
    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12 bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-2xl p-6 sm:p-8">
        
        @if (! $isHeaderHidden)
            <!-- Navigation Sidebar (Vertical Tabs) -->
            <aside class="md:col-span-3">
                <div @class([
                    'sticky top-24' => $isSticky,
                ])>
                    <ol
                        @if (filled($label = $getLabel()))
                            aria-label="{{ $label }}"
                        @endif
                        role="list"
                        x-cloak
                        x-ref="header"
                        class="flex flex-col gap-1"
                    >
                        @foreach ($steps as $step)
                            <li class="relative">
                                <button
                                    type="button"
                                    x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                                    x-on:click="step = @js($step->getKey())"
                                    x-bind:disabled="! isStepAccessible(@js($step->getKey())) || @js($previousAction->isDisabled())"
                                    class="relative flex items-center w-full py-2.5 px-3 text-left rounded-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                                    x-bind:class="{
                                        'bg-gray-100 dark:bg-gray-800': getStepIndex(step) === {{ $loop->index }},
                                        'hover:bg-gray-50 dark:hover:bg-gray-800/50': getStepIndex(step) !== {{ $loop->index }}
                                    }"
                                >
                                    <span class="flex items-center shrink-0 mr-3" aria-hidden="true">
                                        @php
                                            $completedIcon = $step->getCompletedIcon();
                                        @endphp

                                        <!-- Completed Icon -->
                                        <div x-show="getStepIndex(step) > {{ $loop->index }}" x-cloak>
                                            {{
                                                \Filament\Support\generate_icon_html(
                                                    $completedIcon ?? \Filament\Support\Icons\Heroicon::OutlinedCheck,
                                                    alias: filled($completedIcon) ? null : \Filament\Schemas\View\SchemaIconAlias::COMPONENTS_WIZARD_COMPLETED_STEP,
                                                    attributes: new \Illuminate\View\ComponentAttributeBag([
                                                        'class' => 'w-5 h-5 text-primary-600 dark:text-primary-500',
                                                    ]),
                                                    size: \Filament\Support\Enums\IconSize::Medium,
                                                )
                                            }}
                                        </div>

                                        <!-- Active/Pending Icon -->
                                        <div x-show="getStepIndex(step) === {{ $loop->index }}" x-cloak>
                                            @if (filled($icon = $step->getIcon()))
                                                {{
                                                    \Filament\Support\generate_icon_html(
                                                        $icon,
                                                        attributes: new \Illuminate\View\ComponentAttributeBag([
                                                            'class' => 'w-5 h-5 text-gray-900 dark:text-white',
                                                        ]),
                                                        size: \Filament\Support\Enums\IconSize::Medium,
                                                    )
                                                }}
                                            @else
                                                <span class="flex items-center justify-center w-5 h-5 text-sm font-bold text-gray-900 dark:text-white">
                                                    {{ $loop->index + 1 }}
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Future Icon -->
                                        <div x-show="getStepIndex(step) < {{ $loop->index }}" x-cloak>
                                            @if (filled($icon = $step->getIcon()))
                                                {{
                                                    \Filament\Support\generate_icon_html(
                                                        $icon,
                                                        attributes: new \Illuminate\View\ComponentAttributeBag([
                                                            'class' => 'w-5 h-5 text-gray-400 dark:text-gray-500',
                                                        ]),
                                                        size: \Filament\Support\Enums\IconSize::Medium,
                                                    )
                                                }}
                                            @else
                                                <span class="flex items-center justify-center w-5 h-5 text-sm font-medium text-gray-400 dark:text-gray-500">
                                                    {{ $loop->index + 1 }}
                                                </span>
                                            @endif
                                        </div>
                                    </span>
                                    
                                    <span class="flex flex-col min-w-0">
                                        <span 
                                            class="text-sm font-bold tracking-tight transition-colors duration-200"
                                            x-bind:class="{
                                                'text-gray-900 dark:text-white': getStepIndex(step) === {{ $loop->index }},
                                                'text-gray-700 dark:text-gray-300': getStepIndex(step) > {{ $loop->index }},
                                                'text-gray-400 dark:text-gray-500': getStepIndex(step) < {{ $loop->index }},
                                            }"
                                        >
                                            {{ $step->getLabel() }}
                                        </span>
                                        @if (filled($description = $step->getDescription()))
                                            <span 
                                                class="text-xs mt-0.5 line-clamp-2 transition-colors duration-200"
                                                x-bind:class="{
                                                    'text-gray-500 dark:text-gray-400': getStepIndex(step) === {{ $loop->index }},
                                                    'text-gray-400 dark:text-gray-500': getStepIndex(step) !== {{ $loop->index }},
                                                }"
                                            >
                                                {{ $description }}
                                            </span>
                                        @endif
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>

                    <!-- Simple Progress Indicator at the bottom of navigation -->
                    <div class="mt-8 px-3">
                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <span x-text="'Step ' + (getStepIndex(step) + 1) + ' of {{ count($steps) }}'"></span>
                        </div>
                        <div class="mt-2 h-1 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                            <div 
                                class="h-full bg-primary-600 dark:bg-primary-500 transition-all duration-300 ease-out rounded-full"
                                x-bind:style="'width: ' + (((getStepIndex(step) + 1) / {{ count($steps) }}) * 100) + '%'"
                            ></div>
                        </div>
                    </div>

                </div>
            </aside>
        @endif

        <!-- Form Content Area -->
        <div class="md:col-span-9 flex flex-col gap-8">
            @foreach ($steps as $step)
                {{ $step }}
            @endforeach

            <!-- Footer / Actions -->
            <div x-cloak class="fi-sc-wizard-footer flex items-center justify-between gap-3 pt-2">
                <div class="flex items-center gap-3">
                    <div
                        x-cloak
                        @if (! $previousAction->isDisabled())
                            x-on:click="goToPreviousStep"
                        @endif
                        x-show="! isFirstStep()"
                    >
                        {{ $previousAction }}
                    </div>

                    <div x-show="isFirstStep()">
                        {{ $getCancelAction() }}
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div
                        x-cloak
                        @if (! $nextAction->isDisabled())
                            x-on:click="requestNextStep()"
                        @endif
                        x-bind:class="{ 'hidden': isLastStep() }"
                        wire:loading.class="fi-disabled"
                    >
                        {{ $nextAction }}
                    </div>

                    <div x-bind:class="{ 'hidden': ! isLastStep() }">
                        {{ $getSubmitAction() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

