<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $ticket_label_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketLabelTicket extends Pivot
{
    /**
     * @var string
     */
    protected $table = 'ticket_label_ticket';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<TicketLabel, $this> */
    public function label(): BelongsTo
    {
        return $this->belongsTo(TicketLabel::class, 'ticket_label_id');
    }
}
