<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\Ticket;

use Illuminate\Support\Str;
use WireNinja\Accelerator\Contracts\HasHandle;
use WireNinja\Accelerator\Model\Ticket;

class GenerateTicketNumberAction implements HasHandle
{
    public function handle(): string
    {
        do {
            $candidate = sprintf('TKT-%s-%s', now()->format('YmdHis'), strtoupper(Str::random(4)));
        } while (Ticket::query()->withoutGlobalScopes()->where('ticket_number', $candidate)->exists());

        return $candidate;
    }
}
