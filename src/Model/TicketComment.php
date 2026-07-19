<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;
use WireNinja\Accelerator\Model\Scopes\ExcludeArchivedScope;
use WireNinja\Accelerator\Support\UserModel;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $archived_at
 * @property int|null $archived_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Model|null $user
 */
#[ScopedBy([ExcludeArchivedScope::class])]
class TicketComment extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Model, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::className());
    }

    /** @return BelongsTo<Model, $this> */
    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::className(), 'archived_by');
    }
}
