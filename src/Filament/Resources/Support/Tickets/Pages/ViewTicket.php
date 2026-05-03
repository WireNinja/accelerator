<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Resources\Support\Tickets\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\Concerns\HasTicketTimeline;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;

class ViewTicket extends ViewRecord
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
    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit Tiket')
                ->icon('lucide-pencil')
                ->url(fn (): string => TicketResource::getUrl('edit', ['record' => $this->getRecord()]))
                ->authorize('update'),
        ];
    }
}
