<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\Ticket;

use Illuminate\Support\Facades\DB;
use WireNinja\Accelerator\Contracts\HasHandle;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\Ticket;

class ArchiveTicketAction implements HasHandle
{
    public function handle(Ticket $ticket, AcceleratedUser $archivedBy): Ticket
    {
        return DB::transaction(function () use ($ticket, $archivedBy): Ticket {
            /**
             * @var Ticket $ticket
             */
            $ticket = Ticket::query()
                ->withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if (filled($ticket->archived_at)) {
                return $ticket;
            }

            $ticket->forceFill([
                'archived_at' => now(),
                'archived_by' => $archivedBy->id,
                'last_activity_at' => now(),
            ])->save();

            $ticket->comments()
                ->withoutGlobalScopes()
                ->whereNull('archived_at')
                ->update([
                    'archived_at' => now(),
                    'archived_by' => $archivedBy->id,
                    'updated_at' => now(),
                ]);

            return $ticket->fresh() ?? $ticket;
        });
    }
}
