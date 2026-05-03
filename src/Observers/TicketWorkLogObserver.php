<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Observers;

use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Model\TicketWorkLog;

class TicketWorkLogObserver
{
    public function creating(TicketWorkLog $workLog): void
    {
        if (blank($workLog->user_id)) {
            $workLog->user_id = mustUser()->id;
        }

        if (blank($workLog->logged_at)) {
            $workLog->logged_at = now();
        }
    }

    public function created(TicketWorkLog $workLog): void
    {
        $this->touchTicket($workLog);
    }

    public function updated(TicketWorkLog $workLog): void
    {
        $this->touchTicket($workLog);
    }

    public function deleted(TicketWorkLog $workLog): void
    {
        $this->touchTicket($workLog);
    }

    private function touchTicket(TicketWorkLog $workLog): void
    {
        Ticket::query()
            ->whereKey($workLog->ticket_id)
            ->update([
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
