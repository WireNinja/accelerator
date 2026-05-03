<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Observers;

use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Model\TicketRelation;

class TicketRelationObserver
{
    public function creating(TicketRelation $relation): void
    {
        if (blank($relation->created_by)) {
            $relation->created_by = mustUser()->id;
        }
    }

    public function created(TicketRelation $relation): void
    {
        $this->touchTicket($relation);
    }

    public function deleted(TicketRelation $relation): void
    {
        $this->touchTicket($relation);
    }

    private function touchTicket(TicketRelation $relation): void
    {
        Ticket::query()
            ->whereKey($relation->ticket_id)
            ->update([
                'last_activity_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
