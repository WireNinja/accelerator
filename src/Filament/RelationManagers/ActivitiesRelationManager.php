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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;
use WireNinja\Accelerator\Support\ActivityLog\AuditConfig;

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
                    ->description(fn (Activity $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Keterangan')
                    ->state(fn (Activity $record): string => $this->formatActivityDescription($record))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('event')
                    ->label('Event Teknis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $this->formatEventLabel($state))
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Aktor')
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
                        ->slideOver()
                        ->modalHeading('Detail Log Aktivitas')
                        ->modalIcon('lucide-history')
                        ->schema([
                            TextEntry::make('activity_summary')
                                ->hiddenLabel()
                                ->state(fn (Activity $record): HtmlString => $this->buildActivitySummary($record))
                                ->html(),
                            TextEntry::make('causer_card')
                                ->hiddenLabel()
                                ->state(fn (Activity $record): HtmlString => $this->buildCauserCard($record->causer))
                                ->html(),
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
        $availableKeys = collect(array_keys($attributes))
            ->merge(array_keys($old))
            ->unique()
            ->values();
        $configuredKeys = $this->getConfiguredChangeKeys();
        $keys = $configuredKeys === []
            ? $availableKeys->sort()->values()
            : collect($configuredKeys)
                ->filter(fn (string $key): bool => $availableKeys->contains($key))
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
     * @return array<int, string>
     */
    private function getConfiguredChangeKeys(): array
    {
        $ownerRecord = $this->getOwnerRecord();

        return [
            ...AuditConfig::attributes($ownerRecord),
            ...array_keys(AuditConfig::relationships($ownerRecord)),
        ];
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
        $labels = AuditConfig::attributeLabels($this->getOwnerRecord());

        if (isset($labels[$attribute])) {
            return $labels[$attribute];
        }

        $relationships = AuditConfig::relationships($this->getOwnerRecord());

        if (isset($relationships[$attribute])) {
            return (string) Str::of($attribute)
                ->replace('_', ' ')
                ->headline();
        }

        return (string) Str::of($attribute)
            ->replace('.', ' / ')
            ->replace('_', ' ')
            ->headline();
    }

    private function formatActivityDescription(Activity $activity): string
    {
        $description = trim($activity->description);
        $event = trim((string) $activity->event);

        if (($description === '') || (Str::lower($description) === Str::lower($event))) {
            return 'Tidak ada keterangan khusus.';
        }

        return $description;
    }

    private function formatEventLabel(?string $event): string
    {
        return match ($event) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            'restored' => 'Dipulihkan',
            'relationships_updated' => 'Relasi Diperbarui',
            null, '' => '-',
            default => (string) Str::of($event)->replace('_', ' ')->headline(),
        };
    }

    private function buildActivitySummary(Activity $activity): HtmlString
    {
        $time = $activity->created_at?->format('Y-m-d H:i:s') ?? '-';
        $relativeTime = $activity->created_at?->diffForHumans() ?? '-';
        $event = $this->formatEventLabel($activity->event);
        $description = $this->formatActivityDescription($activity);

        return new HtmlString(sprintf(
            '<div class="grid gap-3 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
                <div>
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Waktu</div>
                    <div class="mt-1 font-medium text-gray-950 dark:text-white">%s</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">%s</div>
                </div>
                <div>
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Event Teknis</div>
                    <div class="mt-1 text-gray-950 dark:text-white">%s</div>
                </div>
                <div class="sm:col-span-2">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Keterangan</div>
                    <div class="mt-1 text-gray-700 dark:text-gray-300">%s</div>
                </div>
            </div>',
            e($time),
            e($relativeTime),
            e($event),
            e($description),
        ));
    }

    private function buildCauserCard(?Model $causer): HtmlString
    {
        if ($causer === null) {
            return new HtmlString('<div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">Aktivitas dibuat oleh sistem.</div>');
        }

        $name = $this->stringAttribute($causer, 'name') ?? class_basename($causer);
        $email = $this->stringAttribute($causer, 'email');
        $username = $this->stringAttribute($causer, 'username');
        $avatarUrl = $this->causerAvatarUrl($causer);
        $roles = $this->causerRoleNames($causer);
        $initial = Str::of($name)->trim()->substr(0, 1)->upper()->toString();
        $escapedName = e($name);
        $avatar = $avatarUrl !== null
            ? sprintf('<img src="%s" alt="" class="h-16 w-16 rounded-full object-cover ring-1 ring-gray-200 dark:ring-gray-700">', e($avatarUrl))
            : sprintf('<div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 text-lg font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">%s</div>', e($initial));
        $usernameHtml = $username !== null
            ? sprintf('<div class="leading-5 text-gray-500 dark:text-gray-400">@%s</div>', e($username))
            : '';
        $emailHtml = $email !== null
            ? sprintf('<div class="leading-5 text-gray-600 dark:text-gray-300">%s</div>', e($email))
            : '';
        $rolesHtml = $roles === []
            ? '<span class="text-xs text-gray-400 dark:text-gray-500">Role tidak tersedia</span>'
            : collect($roles)
                ->map(fn (string $role): string => sprintf('<span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium leading-none text-gray-700 dark:bg-gray-800 dark:text-gray-200">%s</span>', e($role)))
                ->implode(' ');

        return new HtmlString(<<<HTML
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-4">
                    <div class="shrink-0">{$avatar}</div>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium leading-5 text-gray-950 dark:text-white">{$escapedName}</div>
                        {$usernameHtml}
                        {$emailHtml}
                        <div class="mt-2 flex flex-wrap gap-1">{$rolesHtml}</div>
                    </div>
                </div>
            </div>
            HTML);
    }

    private function stringAttribute(Model $model, string $key): ?string
    {
        $value = data_get($model, $key);

        return is_string($value) && filled($value) ? $value : null;
    }

    private function causerAvatarUrl(Model $causer): ?string
    {
        if (method_exists($causer, 'getFilamentAvatarUrl')) {
            $url = $causer->{'getFilamentAvatarUrl'}();

            if (is_string($url) && filled($url)) {
                return $url;
            }
        }

        return $this->stringAttribute($causer, 'avatar');
    }

    /**
     * @return array<int, string>
     */
    private function causerRoleNames(Model $causer): array
    {
        if (! method_exists($causer, 'getRoleNames')) {
            return [];
        }

        $roles = $causer->{'getRoleNames'}();

        if ($roles instanceof Collection) {
            return $roles
                ->filter(fn (mixed $role): bool => is_string($role) && filled($role))
                ->map(fn (string $role): string => (string) Str::of($role)->replace(['_', '-'], ' ')->headline())
                ->values()
                ->all();
        }

        return [];
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
