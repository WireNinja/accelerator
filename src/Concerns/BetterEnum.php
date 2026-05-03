<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Concerns;

use BackedEnum;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use WireNinja\Accelerator\Constant\PanelColor;

trait BetterEnum
{
    public function is(BackedEnum|string|int|null $candidate): bool
    {
        return $this === static::resolve($candidate);
    }

    public function isNot(BackedEnum|string|int|null $candidate): bool
    {
        return ! $this->is($candidate);
    }

    /**
     * @param  iterable<int, BackedEnum|string|int|null>  $candidates
     */
    public function isAny(iterable $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->is($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<int, BackedEnum|string|int|null>  $candidates
     */
    public function isNone(iterable $candidates): bool
    {
        return ! $this->isAny($candidates);
    }

    public static function has(BackedEnum|string|int|null $candidate): bool
    {
        return static::resolve($candidate) !== null;
    }

    public static function missing(BackedEnum|string|int|null $candidate): bool
    {
        return ! static::has($candidate);
    }

    public static function resolve(BackedEnum|string|int|null $candidate): ?static
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

    public static function resolveOrFail(BackedEnum|string|int $candidate): static
    {
        $resolved = static::resolve($candidate);

        if ($resolved !== null) {
            return $resolved;
        }

        $candidateValue = $candidate instanceof BackedEnum
            ? (string) $candidate->value
            : (string) $candidate;

        throw new InvalidArgumentException(sprintf('Enum [%s] cannot resolve value [%s].', static::class, $candidateValue));
    }

    public static function tryFromName(string $name): ?static
    {
        return static::resolve($name);
    }

    public static function fromName(string $name): static
    {
        return static::resolveOrFail($name);
    }

    public function label(): string
    {
        return static::optionalStringMethodValue($this, 'getLabel') ?? $this->name;
    }

    public function description(): ?string
    {
        return static::optionalStringMethodValue($this, 'getDescription');
    }

    public function icon(): ?string
    {
        return static::optionalStringMethodValue($this, 'getIcon');
    }

    public function color(): string
    {
        return $this->getColor();
    }

    public function getColor(): string
    {
        return static::colorMap()[$this->enumKey()]
            ?? static::colorMap()[$this->name]
            ?? static::defaultColor();
    }

    protected static function defaultColor(): string
    {
        return PanelColor::Gray;
    }

    /**
     * @return array<int|string, string>
     */
    protected static function colorMap(): array
    {
        return [];
    }

    public static function collection(): Collection
    {
        return collect(static::cases());
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (static::cases() as $case) {
            $names[] = $case->name;
        }

        return $names;
    }

    /**
     * @return list<int|string>
     */
    public static function values(): array
    {
        $values = [];

        foreach (static::cases() as $case) {
            $values[] = $case->value;
        }

        return $values;
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (static::cases() as $case) {
            $options[static::caseKey($case)] = static::optionalStringMethodValue($case, 'getLabel') ?? $case->name;
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (static::cases() as $case) {
            $labels[static::caseKey($case)] = static::optionalStringMethodValue($case, 'getLabel') ?? $case->name;
        }

        return $labels;
    }

    /**
     * @return array<int|string, string|null>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (static::cases() as $case) {
            $descriptions[static::caseKey($case)] = static::optionalStringMethodValue($case, 'getDescription');
        }

        return $descriptions;
    }

    /**
     * @return array<int|string, string|null>
     */
    public static function icons(): array
    {
        $icons = [];

        foreach (static::cases() as $case) {
            $icons[static::caseKey($case)] = static::optionalStringMethodValue($case, 'getIcon');
        }

        return $icons;
    }

    /**
     * @return array<int|string, string>
     */
    public static function colors(): array
    {
        $colors = [];

        foreach (static::cases() as $case) {
            $colors[static::caseKey($case)] = $case->getColor();
        }

        return $colors;
    }

    /**
     * @return list<array{name: string, value: int|string, label: string, description: string|null, icon: string|null, color: string}>
     */
    public static function asArray(): array
    {
        $items = [];

        foreach (static::cases() as $case) {
            $items[] = $case->toArray();
        }

        return $items;
    }

    public static function asCollection(): Collection
    {
        return collect(static::asArray());
    }

    /**
     * @return array{name: string, value: int|string, label: string, description: string|null, icon: string|null, color: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->enumKey(),
            'label' => $this->label(),
            'description' => $this->description(),
            'icon' => $this->icon(),
            'color' => $this->getColor(),
        ];
    }

    protected function enumKey(): int|string
    {
        return $this->value;
    }

    protected static function caseKey(BackedEnum $case): int|string
    {
        return $case->value;
    }

    private static function optionalStringMethodValue(object $case, string $method): ?string
    {
        if (! is_callable([$case, $method])) {
            return null;
        }

        $value = call_user_func([$case, $method]);

        return is_string($value) ? $value : null;
    }
}
