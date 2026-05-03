<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int $ticket_board_id
 * @property string $name
 * @property string $slug
 * @property int $position
 * @property int|null $wip_limit
 * @property bool $is_done
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketBoardColumn extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'wip_limit' => 'integer',
            'is_done' => 'boolean',
        ];
    }

    /** @return BelongsTo<TicketBoard, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TicketBoard::class, 'ticket_board_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
