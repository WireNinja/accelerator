<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Actions\Ticket;

use WireNinja\Accelerator\Contracts\HasHandle;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Model\TicketBoardColumn;

class PrepareTicketForCreateAction implements HasHandle
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $boardIdFromQuery = request()->integer('ticket_board_id');
        if ($boardIdFromQuery > 0 && ($data['ticket_board_id'] ?? 0) <= 0) {
            $data['ticket_board_id'] = $boardIdFromQuery;
        }

        if (($data['ticket_board_id'] ?? 0) <= 0) {
            $data['ticket_board_id'] = TicketBoard::query()->where('is_default', true)->value('id')
                ?? TicketBoard::query()->value('id');
        }

        if (($data['ticket_board_id'] ?? 0) > 0 && ($data['ticket_board_column_id'] ?? 0) <= 0) {
            $firstBoardColumnId = TicketBoardColumn::query()
                ->where('ticket_board_id', $data['ticket_board_id'])
                ->orderBy('position')
                ->value('id');

            if (filled($firstBoardColumnId)) {
                $data['ticket_board_column_id'] = $firstBoardColumnId;
            }
        }

        $authUser = mustUser();

        if (blank($data['reporter_id'] ?? null)) {
            $data['reporter_id'] = $authUser->id;
        }

        if (blank($data['status'] ?? null)) {
            $data['status'] = TicketStatusEnum::Open->value;
        }

        if (blank($data['last_activity_at'] ?? null)) {
            $data['last_activity_at'] = now();
        }

        return $data;
    }
}
