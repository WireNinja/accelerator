<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Enums\Concerns;

use BackedEnum;
use Illuminate\Support\Collection;

trait InteractsWithStateFlow
{
    /**
     * @TODO implement real proper flow state.
     *
     * @return array<int|string, list<BackedEnum|string|int>>
     */
    abstract public static function stateFlowMap(): array;

    public function canTransitionTo(BackedEnum|string|int|null $target): bool
    {
        $resolvedTarget = static::resolveStateTransition($target);

        if ($resolvedTarget === null) {
            return false;
        }

        foreach ($this->allowedTransitions() as $allowedTransition) {
            if ($allowedTransition === $resolvedTarget) {
                return true;
            }
        }

        return false;
    }

    public function cannotTransitionTo(BackedEnum|string|int|null $target): bool
    {
        return ! $this->canTransitionTo($target);
    }

    /**
     * @return Collection<int, self>
     */
    public function allowedTransitions(): Collection
    {
        $configuredTransitions = static::stateFlowMap()[$this->stateFlowKey()] ?? [];
        /** @var array<int, self> $resolvedTransitions */
        $resolvedTransitions = [];

        foreach ($configuredTransitions as $configuredTransition) {
            $resolvedTransition = static::resolveStateTransition($configuredTransition);

            if ($resolvedTransition !== null) {
                $resolvedTransitions[] = $resolvedTransition;
            }
        }

        /** @var Collection<int, self> $resolvedTransitionsCollection */
        $resolvedTransitionsCollection = collect($resolvedTransitions);

        return $resolvedTransitionsCollection;
    }

    /**
     * @return Collection<int, self>
     */
    public function blockedTransitions(): Collection
    {
        /** @var array<int, self> $blockedTransitions */
        $blockedTransitions = [];

        foreach (static::cases() as $case) {
            if ($case === $this) {
                continue;
            }

            if ($this->cannotTransitionTo($case)) {
                $blockedTransitions[] = $case;
            }
        }

        /** @var Collection<int, self> $blockedTransitionsCollection */
        $blockedTransitionsCollection = collect($blockedTransitions);

        return $blockedTransitionsCollection;
    }

    public function hasStateFlowRules(): bool
    {
        return static::stateFlowMap() !== [];
    }

    public function isTerminalState(): bool
    {
        return $this->hasStateFlowRules() && $this->allowedTransitions()->isEmpty();
    }

    protected function stateFlowKey(): int|string
    {
        return $this->value;
    }

    protected static function resolveStateTransition(BackedEnum|string|int|null $candidate): ?static
    {
        if ($candidate instanceof static) {
            return $candidate;
        }

        if ($candidate instanceof BackedEnum) {
            $candidate = $candidate->value;
        }

        foreach (static::cases() as $case) {
            if ($case->name === $candidate) {
                return $case;
            }

            if ($case->value === $candidate) {
                return $case;
            }
        }

        return null;
    }
}
