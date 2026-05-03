<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use WireNinja\Accelerator\Enums\Ticket\TicketTaskStatusEnum;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $created_by
 * @property int|null $assignee_id
 * @property string $title
 * @property string|null $description
 * @property TicketTaskStatusEnum $status
 * @property int $position
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketTask extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => TicketTaskStatusEnum::class,
            'position' => 'integer',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'created_by');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'assignee_id');
    }
}
