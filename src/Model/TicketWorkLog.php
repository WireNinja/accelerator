<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use WireNinja\Accelerator\Observers\TicketWorkLogObserver;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property int $minutes_spent
 * @property string $notes
 * @property CarbonImmutable|null $logged_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[ObservedBy([TicketWorkLogObserver::class])]
class TicketWorkLog extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'minutes_spent' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class);
    }
}
