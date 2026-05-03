<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $created_by
 * @property string $name
 * @property string $slug
 * @property string $color_hex
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketLabel extends Model
{
    /** @return BelongsTo<AcceleratedUser, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'created_by');
    }

    /** @return HasMany<TicketLabelTicket, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(TicketLabelTicket::class);
    }

    /** @return BelongsToMany<Ticket, $this, TicketLabelTicket, 'pivot'> */
    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_label_ticket')
            ->using(TicketLabelTicket::class)
            ->withPivot(['id'])
            ->withTimestamps();
    }
}
