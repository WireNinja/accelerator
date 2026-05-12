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

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
        @if (! $isHeaderHidden)
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
                        class="flex flex-col"
                    >
                        @foreach ($steps as $step)
                            <li
                                class="relative flex items-start group"
                                x-bind:class="{
                                    'fi-active': getStepIndex(step) === {{ $loop->index }},
                                    'fi-completed': getStepIndex(step) > {{ $loop->index }},
                                }"
                            >
                                @if (! $loop->last)
                                    <!-- Connector Line -->
                                    <div 
                                        class="absolute left-8 top-11 -bottom-6 -ml-px w-0.5 transition-colors duration-300" 
                                        aria-hidden="true"
                                        x-bind:class="{
                                            'bg-primary-600 dark:bg-primary-500': getStepIndex(step) > {{ $loop->index }},
                                            'bg-gray-300 dark:bg-gray-700': getStepIndex(step) <= {{ $loop->index }},
                                        }"
                                    ></div>
                                @endif

                                <button
                                    type="button"
                                    x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                                    x-on:click="step = @js($step->getKey())"
                                    x-bind:disabled="! isStepAccessible(@js($step->getKey())) || @js($previousAction->isDisabled())"
                                    class="relative flex items-center w-full py-3 px-4 text-left rounded-xl transition-all duration-200 hover:bg-gray-50 dark:hover:bg-white/5 ring-1 ring-transparent focus:outline-none focus:ring-primary-500"
                                    x-bind:class="{
                                        'bg-primary-50/50 dark:bg-primary-500/10 ring-primary-500/20': getStepIndex(step) === {{ $loop->index }}
                                    }"
                                >
                                    <span class="flex items-center shrink-0" aria-hidden="true">
                                        <span 
                                            class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full border-2 shadow-sm transition-all duration-300"
                                            x-bind:class="{
                                                'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500 scale-105': getStepIndex(step) >= {{ $loop->index }},
                                                'bg-white border-gray-300 dark:bg-gray-800 dark:border-white/20': getStepIndex(step) < {{ $loop->index }},
                                                'ring-4 ring-primary-100 dark:ring-primary-900/30': getStepIndex(step) === {{ $loop->index }},
                                            }"
                                        >
                                            @php
                                                $completedIcon = $step->getCompletedIcon();
                                            @endphp

                                            <!-- Completed Icon -->
                                            <div
                                                x-show="getStepIndex(step) > {{ $loop->index }}"
                                                x-cloak
                                            >
                                                {{
                                                    \Filament\Support\generate_icon_html(
                                                        $completedIcon ?? \Filament\Support\Icons\Heroicon::OutlinedCheck,
                                                        alias: filled($completedIcon) ? null : \Filament\Schemas\View\SchemaIconAlias::COMPONENTS_WIZARD_COMPLETED_STEP,
                                                        attributes: new \Illuminate\View\ComponentAttributeBag([
                                                            'class' => 'w-4 h-4 text-white',
                                                        ]),
                                                        size: \Filament\Support\Enums\IconSize::Small,
                                                    )
                                                }}
                                            </div>

                                            <!-- Active/Pending Icon -->
                                            <div
                                                x-show="getStepIndex(step) === {{ $loop->index }}"
                                                x-cloak
                                            >
                                                @if (filled($icon = $step->getIcon()))
                                                    {{
                                                        \Filament\Support\generate_icon_html(
                                                            $icon,
                                                            attributes: new \Illuminate\View\ComponentAttributeBag([
                                                                'class' => 'w-4 h-4 text-white',
                                                            ]),
                                                            size: \Filament\Support\Enums\IconSize::Small,
                                                        )
                                                    }}
                                                @else
                                                    <span class="text-xs font-bold text-white">
                                                        {{ $loop->index + 1 }}
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Future Icon -->
                                            <div
                                                x-show="getStepIndex(step) < {{ $loop->index }}"
                                                x-cloak
                                            >
                                                @if (filled($icon = $step->getIcon()))
                                                    {{
                                                        \Filament\Support\generate_icon_html(
                                                            $icon,
                                                            attributes: new \Illuminate\View\ComponentAttributeBag([
                                                                'class' => 'w-4 h-4 text-gray-400 dark:text-gray-500',
                                                            ]),
                                                            size: \Filament\Support\Enums\IconSize::Small,
                                                        )
                                                    }}
                                                @else
                                                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500">
                                                        {{ $loop->index + 1 }}
                                                    </span>
                                                @endif
                                            </div>
                                        </span>
                                    </span>
                                    <span class="flex flex-col min-w-0 ml-4">
                                        <span 
                                            class="text-sm font-bold tracking-tight transition-colors duration-200"
                                            x-bind:class="{
                                                'text-primary-600 dark:text-primary-400': getStepIndex(step) === {{ $loop->index }},
                                                'text-gray-900 dark:text-white': getStepIndex(step) > {{ $loop->index }},
                                                'text-gray-500 dark:text-gray-400': getStepIndex(step) < {{ $loop->index }},
                                            }"
                                        >
                                            {{ $step->getLabel() }}
                                        </span>
                                        @if (filled($description = $step->getDescription()))
                                            <span 
                                                class="text-xs transition-colors duration-200 mt-0.5 line-clamp-2"
                                                x-bind:class="{
                                                    'text-primary-600/70 dark:text-primary-400/70': getStepIndex(step) === {{ $loop->index }},
                                                    'text-gray-500 dark:text-gray-400': getStepIndex(step) !== {{ $loop->index }},
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
                </div>
            </aside>
        @endif

        <div class="md:col-span-9 flex flex-col gap-6">
            @foreach ($steps as $step)
                {{ $step }}
            @endforeach

            <div x-cloak class="fi-sc-wizard-footer flex items-center justify-between gap-3 pt-6 border-t border-gray-200 dark:border-white/10">
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

