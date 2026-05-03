<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Model\Ticket;
use WireNinja\Accelerator\Notifications\TicketOverdueNotification;

#[Signature('ticket:notify-overdue')]
#[Description('Kirim notifikasi ke penanggung jawab tiket yang melewati tenggat waktu')]
class NotifyOverdueTicketsCommand extends Command
{
    public function handle(): int
    {
        $resolvedStatuses = [
            TicketStatusEnum::Resolved->value,
            TicketStatusEnum::Closed->value,
        ];

        $overdue = Ticket::query()
            ->withoutGlobalScopes()
            ->with('assigneeUser')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', $resolvedStatuses)
            ->whereNull('archived_at')
            ->where(fn ($q) => $q
                ->whereNull('sla_notified_at')
                ->orWhere('sla_notified_at', '<', now()->subDay()))
            ->get();

        $notified = 0;

        foreach ($overdue as $ticket) {
            /** @var Ticket $ticket */
            $assignee = $ticket->assigneeUser;

            if ($assignee instanceof AcceleratedUser) {
                $assignee->notify(new TicketOverdueNotification($ticket));
            }

            $ticket->timestamps = false;
            $ticket->sla_notified_at = now();
            $ticket->save();

            $notified++;
        }

        $this->info("Notifikasi terkirim untuk {$notified} tiket overdue.");

        return self::SUCCESS;
    }
}
