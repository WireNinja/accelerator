<?php

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Override;
use WireNinja\Accelerator\Actions\Ticket\ArchiveTicketAction;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Concerns\HasTicketTimeline;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;
use WireNinja\Accelerator\Model\Ticket;

class EditTicket extends EditRecord
{
    use HasTicketTimeline;

    protected static string $resource = TicketResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
                Section::make('Riwayat Aktivitas')
                    ->description('Urutan perubahan penting dan percakapan terbaru pada tiket ini.')
                    ->icon('lucide-chart-gantt')
                    ->components([
                        View::make('accelerator::filament.tickets.timeline')
                            ->viewData([
                                'entries' => $this->getTimelineEntries(),
                            ]),
                    ]),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    /**
     * @return Action[]
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')
                ->label('Arsipkan')
                ->icon('lucide-archive')
                ->color('danger')
                ->requiresConfirmation()
                ->authorize('delete')
                ->action(function (Ticket $record): void {
                    app(ArchiveTicketAction::class)->handle($record, mustUser());

                    $this->redirect(TicketResource::getUrl('index'));
                }),
        ];
    }
}
