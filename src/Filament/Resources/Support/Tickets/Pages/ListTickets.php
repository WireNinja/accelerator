<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Override;
use WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;
use WireNinja\Accelerator\Support\Ticket\TicketVisibility;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    /**
     * @return CreateAction[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    #[Override]
    public function getTabs(): array
    {
        $authUser = mustUser();
        $isStaff = TicketVisibility::canManageRouting($authUser)
            || TicketVisibility::canViewAll($authUser);

        $tabs = [
            'all' => Tab::make('Semua')
                ->icon('lucide-list'),
            'open' => Tab::make('Open')
                ->icon('lucide-circle-dot')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatusEnum::Open)),
            'in_progress' => Tab::make('In Progress')
                ->icon('lucide-loader')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatusEnum::InProgress)),
            'waiting_client' => Tab::make('Menunggu Client')
                ->icon('lucide-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatusEnum::WaitingClient)),
            'resolved' => Tab::make('Resolved')
                ->icon('lucide-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    TicketStatusEnum::Resolved,
                    TicketStatusEnum::Closed,
                ])),
        ];

        if ($isStaff) {
            $tabs['urgent'] = Tab::make('Urgent')
                ->icon('lucide-flame')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('priority', TicketPriorityEnum::Urgent)
                    ->whereNotIn('status', [TicketStatusEnum::Resolved, TicketStatusEnum::Closed]));

            $tabs['unassigned'] = Tab::make('Belum Ditugaskan')
                ->icon('lucide-user-x')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('assignee_id')
                    ->whereNotIn('status', [TicketStatusEnum::Resolved, TicketStatusEnum::Closed]));

            $tabs['my_assigned'] = Tab::make('Ditugaskan ke Saya')
                ->icon('lucide-user-check')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assignee_id', $authUser->id));
        } else {
            $tabs['my_tickets'] = Tab::make('Tiket Saya')
                ->icon('lucide-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('reporter_id', $authUser->id));
        }

        return $tabs;
    }
}
