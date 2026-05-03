<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property int|null $added_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketWatcher extends Pivot
{
    /**
     * @var string
     */
    protected $table = 'ticket_watchers';

    /**
     * @var bool
     */
    public $incrementing = true;

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'user_id');
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'added_by');
    }
}
