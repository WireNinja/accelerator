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
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class TicketBoard extends Model
{
    #[Override]
    protected static function booted(): void
    {
        static::saving(function (self $ticketBoard): void {
            if (! $ticketBoard->is_default) {
                return;
            }

            $otherBoards = self::query();

            if ($ticketBoard->exists) {
                $otherBoards->whereKeyNot($ticketBoard->getKey());
            }

            $otherBoards->update(['is_default' => false]);
        });
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return BelongsTo<AcceleratedUser, $this> */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(AcceleratedUser::class, 'created_by');
    }

    /** @return HasMany<TicketBoardColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(TicketBoardColumn::class);
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
