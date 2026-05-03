<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Observers;

use Illuminate\Support\Str;
use WireNinja\Accelerator\Actions\Ticket\GenerateTicketNumberAction;
use WireNinja\Accelerator\Model\Ticket;

class TicketObserver
{
    public function __construct(private readonly GenerateTicketNumberAction $generateTicketNumber) {}

    public function creating(Ticket $ticket): void
    {
        if (blank($ticket->uuid)) {
            $ticket->uuid = (string) Str::uuid();
        }

        if (blank($ticket->ticket_number)) {
            $ticket->ticket_number = $this->generateTicketNumber->handle();
        }

        if (blank($ticket->reporter_id)) {
            $ticket->reporter_id = mustUser()->id;
        }
    }
}
