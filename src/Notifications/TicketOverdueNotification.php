<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Notifications;

use Illuminate\Notifications\Notification;
use WireNinja\Accelerator\Model\Ticket;

class TicketOverdueNotification extends Notification
{
    public function __construct(
        public readonly Ticket $ticket
    ) {}

    /**
     * @return string[]
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'ticket_title' => $this->ticket->title,
            'message' => sprintf('Tiket [%s] melewati tenggat waktu.', $this->ticket->ticket_number),
            'type' => 'ticket_overdue',
        ];
    }
}
