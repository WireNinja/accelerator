<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Filament;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;

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
     * Entry point untuk memetik data berdasarkan field 'Get' tertentu.
     */
    public static function pluck(Get $get, string $field): static
    {
        return new static($get, $field);
    }

    /**
     * Menentukan model yang akan diquery.
     *
     * @param  class-string<Model>  $model
     */
    public function from(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Alias untuk method from().
     *
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        return $this->from($model);
    }

    /**
     * Menentukan kolom label untuk pluck.
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Menentukan kolom value/key untuk pluck.
     */
    public function value(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Menambahkan modifikasi query kustom.
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

        // TODO(deep-analysis): silent return [] saat model belum di-set bisa membingungkan user
        // (dropdown tampak kosong tanpa pesan). Pertimbangkan throw RuntimeException kalau
        // memang minta `model(null)` untuk fail-fast.
        if ($this->model === null) {
            return [];
        }

        $query = $this->model::query();

        if ($this->modifyQuery instanceof Closure) {
            ($this->modifyQuery)($query);
        }

        // Hardcoded 'id' adalah konvensi proyek: SEMUA model di project ini pakai `id` sebagai
        // primary key. Bila ada kebutuhan non-`id`, refactor harus dimulai dari standardisasi
        // primary key, bukan dari helper ini.
        return $query->whereIn('id', (array) $ids)
            ->pluck($this->label, $this->value)
            ->toArray();
    }
}
