<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model\Concerns;

use BadMethodCallException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use UnexpectedValueException;
use WireNinja\Accelerator\Support\PhpDocPropertyTypeResolver;

trait HasTypedColumnMethods
{
    /** @var array<string, array{raw: string, normalized: array<int, string>}>|null */
    private ?array $typedColumnPropertyDefinitions = null;

    /** @var array<int, string>|null */
    private ?array $typedColumnNames = null;

    public function __call($method, $parameters): mixed
    {
        if (is_string($method) && str_starts_with($method, 'setColumn') && ($method !== 'setColumn')) {
            return $this->handleSetColumnMethod($method, $parameters);
        }

        if (is_string($method) && str_starts_with($method, 'getColumn') && ($method !== 'getColumn')) {
            return $this->handleGetColumnMethod($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function handleSetColumnMethod(string $method, array $parameters): static
    {
        if (count($parameters) !== 1) {
            throw new InvalidArgumentException("Method [{$method}] expects exactly one argument.");
        }

        $column = $this->resolveColumnNameFromMethod($method, 'setColumn');
        $definition = $this->getTypedColumnDefinition($column);

        $this->assertValueMatchesDefinition($column, $parameters[0], $definition['raw'], $definition['normalized']);
        $this->setAttribute($column, $parameters[0]);

        return $this;
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function handleGetColumnMethod(string $method, array $parameters): mixed
    {
        if ($parameters !== []) {
            throw new InvalidArgumentException("Method [{$method}] does not accept any arguments.");
        }

        $column = $this->resolveColumnNameFromMethod($method, 'getColumn');
        $definition = $this->getTypedColumnDefinition($column);
        $value = $this->getAttribute($column);

        $this->assertValueMatchesDefinition(
            column: $column,
            value: $value,
            rawType: $definition['raw'],
            normalizedTypes: $definition['normalized'],
            exceptionClass: UnexpectedValueException::class,
        );

        return $value;
    }

    private function resolveColumnNameFromMethod(string $method, string $prefix): string
    {
        $suffix = Str::after($method, $prefix);

        if ($suffix === '') {
            throw new BadMethodCallException("Method [{$method}] does not target a valid column.");
        }

        return Str::snake($suffix);
    }

    /**
     * @return array{raw: string, normalized: array<int, string>}
     */
    private function getTypedColumnDefinition(string $column): array
    {
        if (! in_array($column, $this->getTypedColumnNames(), true)) {
            throw new BadMethodCallException("Column [{$column}] does not exist on table [{$this->getTable()}].");
        }

        $definitions = $this->getTypedColumnPropertyDefinitions();

        if (! array_key_exists($column, $definitions)) {
            throw new BadMethodCallException("Column [{$column}] is missing from model PHPDoc. Run [php artisan accelerator:model-doc ".class_basename(static::class).' --write].');
        }

        return $definitions[$column];
    }

    /**
     * @return array<string, array{raw: string, normalized: array<int, string>}>
     */
    private function getTypedColumnPropertyDefinitions(): array
    {
        if ($this->typedColumnPropertyDefinitions !== null) {
            return $this->typedColumnPropertyDefinitions;
        }

        return $this->typedColumnPropertyDefinitions = PhpDocPropertyTypeResolver::resolve(new ReflectionClass($this));
    }

    /** @return array<int, string> */
    private function getTypedColumnNames(): array
    {
        if ($this->typedColumnNames !== null) {
            return $this->typedColumnNames;
        }

        try {
            return $this->typedColumnNames = Schema::getColumnListing($this->getTable());
        } catch (Throwable) {
            return $this->typedColumnNames = array_keys($this->getTypedColumnPropertyDefinitions());
        }
    }

    /**
     * @param  array<int, string>  $normalizedTypes
     * @param  class-string<InvalidArgumentException|UnexpectedValueException>  $exceptionClass
     */
    private function assertValueMatchesDefinition(
        string $column,
        mixed $value,
        string $rawType,
        array $normalizedTypes,
        string $exceptionClass = InvalidArgumentException::class,
    ): void {
        if (in_array('mixed', $normalizedTypes, true)) {
            return;
        }

        if ($value === null) {
            if (in_array('null', $normalizedTypes, true)) {
                return;
            }

            throw new $exceptionClass("Column [{$column}] expects [{$rawType}], received [null].");
        }

        foreach ($normalizedTypes as $type) {
            if (($type !== 'null') && $this->valueMatchesType($value, $type)) {
                return;
            }
        }

        $actualType = is_object($value) ? $value::class : gettype($value);

        throw new $exceptionClass("Column [{$column}] expects [{$rawType}], received [{$actualType}].");
    }

    private function valueMatchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'float' => is_float($value),
            'int' => is_int($value),
            'object' => is_object($value),
            'string' => is_string($value),
            default => $value instanceof $type,
        };
    }
}
