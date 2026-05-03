<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use WireNinja\Accelerator\Enums\Ticket\TicketRelationTypeEnum;
use WireNinja\Accelerator\Observers\TicketRelationObserver;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $related_ticket_id
 * @property TicketRelationTypeEnum $relation_type
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Ticket|null $ticket
 * @property Ticket|null $relatedTicket
 * @property AcceleratedUser|null $createdByUser
 */
#[ObservedBy([TicketRelationObserver::class])]
class TicketRelation extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'relation_type' => TicketRelationTypeEnum::class,
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function relatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'created_by');
    }
}
