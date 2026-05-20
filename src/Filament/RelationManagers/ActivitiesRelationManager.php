<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\RelationManagers;

use BackedEnum;
use DateTimeInterface;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivitiesRelationManager extends RelationManager
{
    #[Override]
    protected static ?string $title = 'Log Aktivitas';

    #[Override]
    protected static string $relationship = 'activities';

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Aktivitas')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('event')
                    ->label('Event')
                    ->badge()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Pelaku')
                    ->placeholder('Sistem')
                    ->searchable(),
                TextColumn::make('attribute_changes_summary')
                    ->label('Perubahan')
                    ->state(fn (Activity $record): string => $this->summarizeChanges($record))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('properties_summary')
                    ->label('Properti')
                    ->state(fn (Activity $record): string => $this->summarizeProperties($record->properties))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalHeading('Detail Log Aktivitas')
                        ->modalIcon('lucide-history')
                        ->schema([
                            Section::make('Informasi Aktivitas')
                                ->icon('lucide-info')
                                ->columns(2)
                                ->components([
                                    TextEntry::make('created_at')
                                        ->label('Waktu')
                                        ->dateTime(),
                                    TextEntry::make('event')
                                        ->label('Event')
                                        ->badge(),
                                    TextEntry::make('causer.name')
                                        ->label('Pelaku')
                                        ->placeholder('Sistem'),
                                    TextEntry::make('description')
                                        ->label('Aktivitas')
                                        ->columnSpanFull(),
                                ]),
                            Section::make('Perbandingan Perubahan')
                                ->icon('lucide-git-compare')
                                ->components([
                                    TextEntry::make('changes_table')
                                        ->hiddenLabel()
                                        ->state(fn (Activity $record): HtmlString => $this->buildChangesTable($record))
                                        ->html(),
                                ]),
                            Section::make('Properti Tambahan')
                                ->icon('lucide-braces')
                                ->components([
                                    TextEntry::make('properties_table')
                                        ->hiddenLabel()
                                        ->state(fn (Activity $record): HtmlString => $this->buildPropertiesTable($record->properties))
                                        ->html(),
                                ]),
                        ]),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('lucide-history')
            ->emptyStateHeading('Belum ada log aktivitas')
            ->emptyStateDescription('Perubahan data akan muncul setelah ada aktivitas yang tercatat.');
    }

    private function summarizeChanges(Activity $record): string
    {
        $rows = $this->getChangeRows($record);

        if ($rows === []) {
            return '-';
        }

        $labels = collect($rows)
            ->take(3)
            ->map(fn (array $row): string => $this->formatAttributeLabel($row['attribute']))
            ->implode(', ');

        $remainingCount = count($rows) - 3;

        if ($remainingCount > 0) {
            return "{$labels} + {$remainingCount} lainnya";
        }

        return $labels;
    }

    /**
     * @param  Collection<string, mixed>|null  $collection
     */
    private function summarizeProperties(?Collection $collection): string
    {
        if (($collection === null) || $collection->isEmpty()) {
            return '-';
        }

        return $collection->keys()
            ->map(fn (mixed $key): string => $this->formatAttributeLabel((string) $key))
            ->implode(', ');
    }

    private function buildChangesTable(Activity $record): HtmlString
    {
        $rows = $this->getChangeRows($record);

        if ($rows === []) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada perubahan atribut yang tercatat.</p>');
        }

        $body = collect($rows)
            ->map(fn (array $row): string => sprintf(
                '<tr class="border-b border-gray-200 dark:border-gray-700"><td class="px-3 py-2 font-medium text-gray-950 dark:text-white">%s</td><td class="px-3 py-2 text-gray-700 dark:text-gray-300">%s</td><td class="px-3 py-2 text-gray-700 dark:text-gray-300">%s</td></tr>',
                e($this->formatAttributeLabel($row['attribute'])),
                $this->formatValueForHtml($row['old']),
                $this->formatValueForHtml($row['new']),
            ))
            ->implode('');

        return new HtmlString(<<<HTML
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Field</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Lama</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Baru</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">{$body}</tbody>
                </table>
            </div>
            HTML);
    }

    /**
     * @param  Collection<string, mixed>|null  $properties
     */
    private function buildPropertiesTable(?Collection $properties): HtmlString
    {
        if (($properties === null) || $properties->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada properti tambahan.</p>');
        }

        $body = $properties
            ->map(fn (mixed $value, mixed $key): string => sprintf(
                '<tr class="border-b border-gray-200 dark:border-gray-700"><td class="px-3 py-2 font-medium text-gray-950 dark:text-white">%s</td><td class="px-3 py-2 text-gray-700 dark:text-gray-300">%s</td></tr>',
                e($this->formatAttributeLabel((string) $key)),
                $this->formatValueForHtml($value),
            ))
            ->implode('');

        return new HtmlString(<<<HTML
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Properti</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-950 dark:text-white">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">{$body}</tbody>
                </table>
            </div>
            HTML);
    }

    /**
     * @return array<int, array{attribute: string, old: mixed, new: mixed}>
     */
    private function getChangeRows(Activity $record): array
    {
        $changes = $record->attribute_changes;

        if (($changes === null) || $changes->isEmpty()) {
            return [];
        }

        $attributes = $this->normalizeAssociativeArray($changes->get('attributes'));
        $old = $this->normalizeAssociativeArray($changes->get('old'));
        $keys = collect(array_keys($attributes))
            ->merge(array_keys($old))
            ->unique()
            ->sort()
            ->values();

        return $keys
            ->map(fn (mixed $key): array => [
                'attribute' => (string) $key,
                'old' => $old[(string) $key] ?? null,
                'new' => $attributes[(string) $key] ?? null,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssociativeArray(mixed $value): array
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    private function formatAttributeLabel(string $attribute): string
    {
        return (string) Str::of($attribute)
            ->replace('.', ' / ')
            ->replace('_', ' ')
            ->headline();
    }

    private function formatValueForHtml(mixed $value): string
    {
        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        if (($value === null) || ($value === '')) {
            return '<span class="text-gray-400 dark:text-gray-500">-</span>';
        }

        if ($value instanceof BackedEnum) {
            return e($value->value);
        }

        if ($value instanceof UnitEnum) {
            return e($value->name);
        }

        if ($value instanceof DateTimeInterface) {
            return e($value->format('Y-m-d H:i:s'));
        }

        if (is_bool($value)) {
            return e($value ? 'Ya' : 'Tidak');
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return e((string) $value);
        }

        if (is_array($value)) {
            return $this->formatArrayForHtml($value);
        }

        return e(get_debug_type($value));
    }

    /**
     * @param  array<mixed>  $value
     */
    private function formatArrayForHtml(array $value): string
    {
        if ($value === []) {
            return '<span class="text-gray-400 dark:text-gray-500">-</span>';
        }

        $isList = array_is_list($value);

        return collect($value)
            ->map(function (mixed $item, mixed $key) use ($isList): string {
                $formattedValue = $this->formatValueForHtml($item);

                if ($isList) {
                    return $formattedValue;
                }

                return '<span class="font-medium">'.e($this->formatAttributeLabel((string) $key)).':</span> '.$formattedValue;
            })
            ->implode('<br>');
    }
}
