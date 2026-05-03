<?php

namespace WireNinja\Accelerator\Support\Filament;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class ResourceContextTableHost implements HasTable
{
    protected ?Table $table = null;

    protected ?Schema $filtersForm = null;

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        protected string $modelClass,
    ) {}

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    public function callTableColumnAction(string $name, string $recordKey): mixed
    {
        return null;
    }

    public function deselectAllTableRecords(): void {}

    public function getActiveTableLocale(): ?string
    {
        return null;
    }

    /**
     * @return array{}
     */
    public function getAllSelectableTableRecordKeys(): array
    {
        return [];
    }

    public function getAllTableRecordsCount(): int
    {
        return 0;
    }

    public function getAllSelectableTableRecordsCount(): int
    {
        return 0;
    }

    public function getTableFilterState(string $name): ?array
    {
        return null;
    }

    public function getTableFilterFormState(string $name): ?array
    {
        return null;
    }

    public function getSelectedTableRecords(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): EloquentCollection|Collection|LazyCollection
    {
        return collect();
    }

    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        return $this->newQuery();
    }

    public function parseTableFilterName(string $name): string
    {
        return $name;
    }

    public function getTableGrouping(): ?Group
    {
        return null;
    }

    public function getMountedTableAction(): ?Action
    {
        return null;
    }

    public function getMountedTableActionForm(): ?Schema
    {
        return null;
    }

    public function getMountedTableActionRecord(): ?Model
    {
        return null;
    }

    public function getMountedTableBulkAction(): ?Action
    {
        return null;
    }

    public function getMountedTableBulkActionForm(): ?Schema
    {
        return null;
    }

    public function getTable(): Table
    {
        return $this->table ??= Table::make($this);
    }

    public function getTableFiltersForm(): Schema
    {
        return $this->filtersForm ??= Schema::make();
    }

    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        return collect();
    }

    public function getTableRecordsPerPage(): int|string|null
    {
        return null;
    }

    public function getTablePage(): int|string
    {
        return 1;
    }

    public function getTableSortColumn(): ?string
    {
        return null;
    }

    public function getTableSortDirection(): ?string
    {
        return null;
    }

    public function getAllTableSummaryQuery(): ?Builder
    {
        return null;
    }

    public function getPageTableSummaryQuery(): ?Builder
    {
        return null;
    }

    public function isTableColumnToggledHidden(string $name): bool
    {
        return false;
    }

    public function getTableRecord(?string $key): Model|array|null
    {
        return null;
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if ($record instanceof Model) {
            return (string) $record->getKey();
        }

        return (string) ($record['id'] ?? '');
    }

    public function toggleTableReordering(): void {}

    public function isTableReordering(): bool
    {
        return false;
    }

    public function isTableLoaded(): bool
    {
        return true;
    }

    public function hasTableSearch(): bool
    {
        return false;
    }

    public function resetTableSearch(): void {}

    public function resetTableColumnSearch(string $column): void {}

    public function getTableSearchIndicator(): Indicator
    {
        return Indicator::make('');
    }

    /**
     * @return array{}
     */
    public function getTableColumnSearchIndicators(): array
    {
        return [];
    }

    public function getFilteredTableQuery(): ?Builder
    {
        return null;
    }

    public function getFilteredSortedTableQuery(): ?Builder
    {
        return null;
    }

    public function getTableQueryForExport(): Builder
    {
        return $this->newQuery();
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function callMountedTableAction(array $arguments = []): mixed
    {
        return null;
    }

    public function mountTableAction(string $name, ?string $record = null, array $arguments = []): mixed
    {
        return null;
    }

    public function replaceMountedTableAction(string $name, ?string $record = null, array $arguments = []): void {}

    public function mountTableBulkAction(string $name, ?array $selectedRecords = null): mixed
    {
        return null;
    }

    public function replaceMountedTableBulkAction(string $name, ?array $selectedRecords = null): void {}

    protected function newQuery(): Builder
    {
        $modelClass = $this->modelClass;

        return $modelClass::query();
    }
}
