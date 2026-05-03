<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Concerns;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketTypeEnum;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Model\TicketBoardColumn;

trait HasTicketTimeline
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getTimelineEntries(): array
    {
        $ticket = $this->getRecord();

        $ticket->loadMissing([
            'activitiesAsSubject.causer',
            'comments.user',
            'workLogs.user',
            'board',
            'boardColumn',
            'reporterUser',
            'assigneeUser',
            'closedByUser',
            'archivedByUser',
        ]);

        $userIds = collect([
            $ticket->reporter_id,
            $ticket->assignee_id,
            $ticket->closed_by,
            $ticket->archived_by,
            ...$ticket->activitiesAsSubject->pluck('causer_id')->filter()->all(),
            ...$ticket->comments->pluck('user_id')->filter()->all(),
            ...$ticket->workLogs->pluck('user_id')->filter()->all(),
        ])
            ->filter()
            ->unique()
            ->values();

        $userNames = AcceleratedUser::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        $entries = [];

        foreach ($ticket->activitiesAsSubject->sortByDesc('created_at') as $activity) {
            $entries[] = $this->formatActivityEntry($activity, $userNames->all());
        }

        foreach ($ticket->comments->sortByDesc('created_at') as $comment) {
            $entries[] = [
                'type' => $comment->is_internal ? 'internal-note' : 'comment',
                'title' => $comment->is_internal ? 'Catatan internal' : 'Komentar client',
                'description' => $comment->body,
                'author' => $comment->user?->name ?? 'Sistem',
                'author_badge' => $comment->is_internal ? 'Internal' : 'Client',
                'author_badge_color' => $comment->is_internal ? 'gray' : 'info',
                'occurred_at' => $comment->created_at,
                'icon' => $comment->is_internal ? 'lucide-sticky-note' : 'lucide-message-square-text',
            ];
        }

        foreach ($ticket->workLogs->sortByDesc('logged_at') as $workLog) {
            $entries[] = [
                'type' => 'work-log',
                'title' => 'Log waktu '.$this->formatMinutes($workLog->minutes_spent),
                'description' => $workLog->notes,
                'author' => $workLog->user?->name ?? 'Sistem',
                'author_badge' => 'Work log',
                'author_badge_color' => 'success',
                'occurred_at' => $workLog->logged_at ?? $workLog->created_at,
                'icon' => 'lucide-briefcase-business',
            ];
        }

        return collect($entries)
            ->sortByDesc('occurred_at')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $userNames
     * @return array<string, mixed>
     */
    protected function formatActivityEntry(Activity $activity, array $userNames): array
    {
        $changes = $activity->attribute_changes->toArray();
        $attributes = $changes['attributes'] ?? [];
        $oldAttributes = $changes['old'] ?? [];

        $summary = [$activity->description];

        if (array_key_exists('status', $attributes)) {
            $summary[] = sprintf(
                'Status: %s → %s',
                $this->formatTicketValue('status', $oldAttributes['status'] ?? null, $userNames),
                $this->formatTicketValue('status', $attributes['status'], $userNames),
            );
        }

        if (array_key_exists('assignee_id', $attributes)) {
            $summary[] = sprintf(
                'Penanggung jawab: %s → %s',
                $this->formatTicketValue('assignee_id', $oldAttributes['assignee_id'] ?? null, $userNames),
                $this->formatTicketValue('assignee_id', $attributes['assignee_id'], $userNames),
            );
        }

        if (array_key_exists('ticket_board_column_id', $attributes)) {
            $summary[] = sprintf(
                'Kolom: %s → %s',
                $this->formatTicketValue('ticket_board_column_id', $oldAttributes['ticket_board_column_id'] ?? null, $userNames),
                $this->formatTicketValue('ticket_board_column_id', $attributes['ticket_board_column_id'], $userNames),
            );
        }

        if (array_key_exists('priority', $attributes)) {
            $summary[] = sprintf(
                'Prioritas: %s → %s',
                $this->formatTicketValue('priority', $oldAttributes['priority'] ?? null, $userNames),
                $this->formatTicketValue('priority', $attributes['priority'], $userNames),
            );
        }

        if (array_key_exists('archived_at', $attributes)) {
            $summary[] = 'Tiket diarsipkan';
        }

        if (count($summary) === 1) {
            $summary[] = 'Perubahan data tiket dicatat oleh sistem.';
        }

        return [
            'type' => 'activity',
            'title' => implode(' | ', $summary),
            'description' => $this->formatActivityChanges($attributes, $oldAttributes, $userNames),
            'author' => $activity->causer?->name ?? 'Sistem',
            'author_badge' => ucfirst((string) $activity->event),
            'author_badge_color' => match ($activity->event) {
                'created' => 'success',
                'deleted' => 'danger',
                default => 'gray',
            },
            'occurred_at' => $activity->created_at,
            'icon' => 'lucide-activity',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $oldAttributes
     * @param  array<int, string>  $userNames
     */
    protected function formatActivityChanges(array $attributes, array $oldAttributes, array $userNames): ?string
    {
        $lines = [];

        foreach (['title', 'description'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $lines[] = sprintf(
                '%s diubah dari "%s" menjadi "%s".',
                $this->labelForField($field),
                $this->formatTicketValue($field, $oldAttributes[$field] ?? null, $userNames),
                $this->formatTicketValue($field, $attributes[$field], $userNames),
            );
        }

        foreach (['ticket_board_id', 'reporter_id', 'closed_by', 'archived_by'] as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $lines[] = sprintf(
                '%s berubah dari %s menjadi %s.',
                $this->labelForField($field),
                $this->formatTicketValue($field, $oldAttributes[$field] ?? null, $userNames),
                $this->formatTicketValue($field, $attributes[$field], $userNames),
            );
        }

        if (array_key_exists('due_at', $attributes)) {
            $lines[] = sprintf(
                '%s berubah dari %s menjadi %s.',
                $this->labelForField('due_at'),
                $this->formatTicketValue('due_at', $oldAttributes['due_at'] ?? null, $userNames),
                $this->formatTicketValue('due_at', $attributes['due_at'], $userNames),
            );
        }

        return blank($lines)
            ? null
            : implode(' ', $lines);
    }

    protected function formatMinutes(int $minutesSpent): string
    {
        if ($minutesSpent < 60) {
            return $minutesSpent.' menit';
        }

        $hours = intdiv($minutesSpent, 60);
        $remainingMinutes = $minutesSpent % 60;

        if ($remainingMinutes === 0) {
            return $hours.' jam';
        }

        return $hours.' jam '.$remainingMinutes.' menit';
    }

    /**
     * @param  array<int, string>  $userNames
     */
    protected function formatTicketValue(string $field, mixed $value, array $userNames): string
    {
        if ($value === null || $value === '') {
            return 'Kosong';
        }

        return match ($field) {
            'status' => TicketStatusEnum::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'priority' => TicketPriorityEnum::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'type' => TicketTypeEnum::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            'ticket_board_id' => TicketBoard::query()->find($value)?->name ?? 'Board #'.(string) $value,
            'ticket_board_column_id' => TicketBoardColumn::query()->find($value)?->name ?? 'Kolom #'.(string) $value,
            'assignee_id', 'reporter_id', 'closed_by', 'archived_by' => $userNames[(string) $value] ?? 'User #'.(string) $value,
            'due_at', 'started_at', 'resolved_at', 'closed_at', 'archived_at' => Str::of((string) $value)->replace('T', ' ')->before('+')->toString(),
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR),
        };
    }

    protected function labelForField(string $field): string
    {
        return match ($field) {
            'title' => 'Judul',
            'description' => 'Keterangan',
            'ticket_board_id' => 'Board',
            'ticket_board_column_id' => 'Kolom',
            'reporter_id' => 'Pengaju',
            'assignee_id' => 'Penanggung jawab',
            'priority' => 'Prioritas',
            'status' => 'Status',
            'type' => 'Jenis',
            'due_at' => 'Target waktu',
            'started_at' => 'Mulai dikerjakan',
            'resolved_at' => 'Diselesaikan',
            'closed_at' => 'Ditutup',
            'closed_by' => 'Penutup tiket',
            'archived_at' => 'Diarsipkan',
            'archived_by' => 'Pengarsip',
            default => Str::headline($field),
        };
    }
}
