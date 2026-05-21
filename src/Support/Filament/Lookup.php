<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-consistent-constructor
 */
class Lookup
{
    protected string $label = 'name';

    protected string $value = 'id';

    protected ?Closure $modifyQuery = null;

    /**
     * @param  class-string<Model>|null  $model
     */
    public function __construct(
        protected Get $get,
        protected string $field,
        protected ?string $model = null,
    ) {}

    /**
     * Entry point for plucking data based on a specific 'Get' field.
     */
    public static function pluck(Get $get, string $field): static
    {
        return new static($get, $field);
    }

    /**
     * Set the model to query.
     *
     * @param  class-string<Model>  $model
     */
    public function from(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Alias for the from() method.
     *
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        return $this->from($model);
    }

    /**
     * Set the label column for pluck.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the value/key column for pluck.
     */
    public function value(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Add a custom query modifier.
     */
    public function modifyQuery(Closure $modifyQuery): static
    {
        $this->modifyQuery = $modifyQuery;

        return $this;
    }

    /**
     * Menjalankan query dan mengembalikan array opsi.
     *
     * @return array<string|int, string>
     */
    public function get(): array
    {
        $ids = ($this->get)($this->field);

        if (blank($ids)) {
            return [];
        }

        // TODO(deep-analysis): silent return [] when model is not set could confuse users
        // (dropdown appears empty without a message). Consider throwing RuntimeException
        // when model(null) is explicitly passed for fail-fast behavior.
        if ($this->model === null) {
            return [];
        }

        $query = $this->model::query();

        if ($this->modifyQuery instanceof Closure) {
            ($this->modifyQuery)($query);
        }

        // Hardcoded 'id' is a project convention: ALL models use `id` as primary key.
        // If a non-`id` key is ever needed, the refactor must start from standardizing
        // primary keys, not from this helper.
        return $query->whereIn('id', (array) $ids)
            ->pluck($this->label, $this->value)
            ->toArray();
    }
}
