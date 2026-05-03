<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use App\Models\DocumentAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Override;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketTypeEnum;
use WireNinja\Accelerator\Model\Scopes\ExcludeArchivedScope;
use WireNinja\Accelerator\Observers\TicketObserver;
use WireNinja\Accelerator\Support\Ticket\TicketVisibility;

/**
 * @property int $id
 * @property string $uuid
 * @property string $ticket_number
 * @property int|null $ticket_board_id
 * @property int|null $ticket_board_column_id
 * @property int $reporter_id
 * @property int|null $assignee_id
 * @property string $title
 * @property string|null $description
 * @property TicketTypeEnum $type
 * @property TicketPriorityEnum $priority
 * @property TicketStatusEnum $status
 * @property int|null $effort_points
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $closed_at
 * @property CarbonImmutable|null $first_response_at
 * @property CarbonImmutable|null $last_activity_at
 * @property int|null $closed_by
 * @property CarbonImmutable|null $archived_at
 * @property int|null $archived_by
 * @property CarbonImmutable|null $sla_notified_at
 * @property bool $is_public
 * @property string|null $dampak_bisnis
 * @property string|null $modul_terkait
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property TicketBoard|null $board
 * @property TicketBoardColumn|null $boardColumn
 * @property AcceleratedUser|null $reporterUser
 * @property AcceleratedUser|null $assigneeUser
 * @property AcceleratedUser|null $closedByUser
 * @property AcceleratedUser|null $archivedByUser
 * @property Collection|TicketComment[] $comments
 * @property Collection|TicketWorkLog[] $workLogs
 * @property Collection|DocumentAttachment[] $attachments
 * @property Collection|TicketRelation[] $outgoingRelations
 * @property Collection|TicketRelation[] $incomingRelations
 * @property Collection|TicketTask[] $tasks
 * @property Collection|TicketLabel[] $labels
 * @property Collection|AcceleratedUser[] $watchers
 * @property Collection|Activity[] $activitiesAsSubject
 *
 * @method static Builder<static> visibleTo(AcceleratedUser $user)
 */
#[ObservedBy([TicketObserver::class])]
#[ScopedBy([ExcludeArchivedScope::class])]
class Ticket extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => TicketTypeEnum::class,
            'priority' => TicketPriorityEnum::class,
            'status' => TicketStatusEnum::class,
            'effort_points' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_response_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'archived_at' => 'datetime',
            'sla_notified_at' => 'datetime',
            'is_public' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('ticket')
            ->logOnly([
                'title',
                'description',
                'ticket_board_id',
                'ticket_board_column_id',
                'reporter_id',
                'assignee_id',
                'type',
                'priority',
                'status',
                'dampak_bisnis',
                'modul_terkait',
                'effort_points',
                'due_at',
                'started_at',
                'resolved_at',
                'closed_at',
                'closed_by',
                'archived_at',
                'archived_by',
            ])
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at', 'last_activity_at'])
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(function (string $eventName): string {
                return match ($eventName) {
                    'created' => 'Tiket dibuat',
                    'updated' => 'Tiket diperbarui',
                    'deleted' => 'Tiket dihapus',
                    default => 'Tiket diperbarui',
                };
            });
    }

    /** @param Builder<Ticket> $query */
    public function scopeVisibleTo(Builder $query, AcceleratedUser $user): void
    {
        if (TicketVisibility::canViewAll($user)) {
            return;
        }

        $canViewOwn = $user->can('ViewOwn:Ticket');
        $canViewAssigned = $user->can('ViewAssigned:Ticket');

        $query->where(function (Builder $q) use ($user, $canViewOwn, $canViewAssigned): void {
            if ($canViewOwn) {
                $q->orWhere('reporter_id', $user->id);
            }

            if ($canViewAssigned) {
                $q->orWhere('assignee_id', $user->id);
            }

            $q->orWhereHas('watchers', fn (Builder $wq): Builder => $wq->where('user_id', $user->id));

            $q->orWhere('is_public', true);
        });
    }

    /** @return BelongsTo<TicketBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    /** @return BelongsTo<TicketBoardColumn, $this> */
    public function boardColumn(): BelongsTo
    {
        return $this->belongsTo(TicketBoardColumn::class, 'ticket_board_column_id');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function reporterUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'reporter_id');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'assignee_id');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'closed_by');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'archived_by');
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketWorkLog, $this> */
    public function workLogs(): HasMany
    {
        return $this->hasMany(TicketWorkLog::class);
    }

    /** @return MorphMany<DocumentAttachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(DocumentAttachment::class, 'attachable');
    }

    /** @return HasMany<TicketRelation, $this> */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'ticket_id');
    }

    /** @return HasMany<TicketRelation, $this> */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'related_ticket_id');
    }

    /** @return HasMany<TicketTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    /** @return BelongsToMany<TicketLabel, $this, TicketLabelTicket, 'pivot'> */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TicketLabel::class, 'ticket_label_ticket')
            ->using(TicketLabelTicket::class)
            ->withPivot(['id'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<AcceleratedUser, $this, TicketWatcher, 'pivot'> */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(AcceleratedUser::class, 'ticket_watchers', 'ticket_id', 'user_id')
            ->using(TicketWatcher::class)
            ->withPivot(['id', 'added_by'])
            ->withTimestamps();
    }
}
