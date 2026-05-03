<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\Ticket;

use Illuminate\Support\Facades\DB;
use WireNinja\Accelerator\Contracts\HasHandle;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Model\Ticket;

class ChangeTicketStatusAction implements HasHandle
{
    public function handle(Ticket $ticket, TicketStatusEnum $status): Ticket
    {
        return DB::transaction(function () use ($ticket, $status): Ticket {
            $lockedTicket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            $attributes = [
                'status' => $status,
            ];

            if ($status === TicketStatusEnum::InProgress && blank($lockedTicket->started_at)) {
                $attributes['started_at'] = now();
            }

            if ($status !== TicketStatusEnum::Open && blank($lockedTicket->first_response_at)) {
                $attributes['first_response_at'] = now();
            }

            if ($status === TicketStatusEnum::Resolved) {
                $attributes['resolved_at'] = now();
            }

            if ($status === TicketStatusEnum::Closed) {
                $attributes['closed_at'] = now();
                $attributes['closed_by'] = mustUser()->id;
            }

            $attributes['last_activity_at'] = now();

            $lockedTicket->forceFill($attributes)->save();

            return $lockedTicket->fresh() ?? $lockedTicket;
        });
    }
}
