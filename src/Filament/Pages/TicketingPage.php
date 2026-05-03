<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UnitEnum;
use WireNinja\Accelerator\Enums\Ticket\TicketTaskStatusEnum;
use WireNinja\Accelerator\Filament\Resources\Support\TicketBoards\TicketBoardResource;
use WireNinja\Accelerator\Filament\Resources\Support\Tickets\TicketResource;
use WireNinja\Accelerator\Model\TicketBoard;
use WireNinja\Accelerator\Support\Ticket\TicketVisibility;

class TicketingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'lucide-ticket';

    protected static ?string $navigationLabel = 'Ticketing';

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 10;

    protected string $view = 'accelerator::filament.pages.ticketing-page';

    public ?TicketBoard $board = null;

    public array $boardOptions = [];

    public string|int|null $selectedBoardId = null;

    public string $createBoardUrl;

    public string $manageBoardsUrl;

    public string $manageTicketsUrl;

    public string $createTicketUrl;

    public bool $isPetugas = false;

    public string $visibilityInfo = '';

    public string $search = '';

    public string $filterType = '';

    public string $filterPriority = '';

    public string $filterDampakBisnis = '';

    public string $filterModulTerkait = '';

    public function updatedSearch(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterType(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterPriority(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterDampakBisnis(): void
    {
        $this->loadBoard();
    }

    public function updatedFilterModulTerkait(): void
    {
        $this->loadBoard();
    }

    public function mount(): void
    {
        $authUser = mustUser();

        $this->isPetugas = TicketVisibility::canManageRouting($authUser)
            || TicketVisibility::canViewAll($authUser);

        $this->visibilityInfo = TicketVisibility::describeAccess($authUser);

        $this->createBoardUrl = TicketBoardResource::getUrl('create');
        $this->manageBoardsUrl = TicketBoardResource::getUrl('index');
        $this->manageTicketsUrl = TicketResource::getUrl('index');

        $boardOptionsQuery = TicketBoard::query()
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (! TicketVisibility::canViewAll($authUser)) {
            $boardOptionsQuery->whereHas('tickets', function (Builder $ticketQuery) use ($authUser): void {
                $ticketQuery->visibleTo($authUser); // @phpstan-ignore method.notFound
            });
        }

        $this->boardOptions = $boardOptionsQuery->pluck('name', 'id')->all();

        $defaultBoardId = TicketBoard::query()
            ->where('is_default', true)
            ->value('id');

        $firstBoardId = collect($this->boardOptions)->keys()->first();

        $this->selectedBoardId = $defaultBoardId ?? $firstBoardId;

        $this->loadBoard();
    }

    public function updatedSelectedBoardId(): void
    {
        $this->loadBoard();
    }

    public function getTicketViewUrl(int $ticketId): string
    {
        return TicketResource::getUrl('view', ['record' => $ticketId]);
    }

    private function loadBoard(): void
    {
        $authUser = mustUser();
        $search = trim($this->search);

        $filterType = $this->filterType;
        $filterPriority = $this->filterPriority;
        $filterDampakBisnis = $this->filterDampakBisnis;
        $filterModulTerkait = $this->filterModulTerkait;

        $withColumns = fn ($query) => $query
            ->orderBy('position')
            ->with([
                'tickets' => function (HasMany $ticketQuery) use ($authUser, $search, $filterType, $filterPriority, $filterDampakBisnis, $filterModulTerkait): void {
                    $ticketQuery
                        ->visibleTo($authUser) // @phpstan-ignore method.notFound
                        ->when($filterType !== '', fn ($q) => $q->where('type', $filterType))
                        ->when($filterPriority !== '', fn ($q) => $q->where('priority', $filterPriority))
                        ->when($filterDampakBisnis !== '', fn ($q) => $q->where('dampak_bisnis', $filterDampakBisnis))
                        ->when($filterModulTerkait !== '', fn ($q) => $q->where('modul_terkait', $filterModulTerkait))
                        ->when($search !== '', function (Builder $q) use ($search): void {
                            $q->where(function (Builder $sub) use ($search): void {
                                $sub->where('ticket_number', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%")
                                    ->orWhere('dampak_bisnis', 'like', "%{$search}%")
                                    ->orWhere('modul_terkait', 'like', "%{$search}%")
                                    ->orWhereHas('assigneeUser', fn ($aq) => $aq->where('name', 'like', "%{$search}%"))
                                    ->orWhereHas('reporterUser', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
                            });
                        })
                        ->orderByDesc('last_activity_at')
                        ->latest('created_at')
                        ->with(['assigneeUser', 'reporterUser', 'labels', 'watchers'])
                        ->withCount([
                            'comments',
                            'tasks as total_tasks',
                            'tasks as done_tasks' => function ($taskQuery): void {
                                $taskQuery->where('status', TicketTaskStatusEnum::Done->value);
                            },
                        ]);
                },
            ]);

        $this->board = TicketBoard::query()
            ->with(['columns' => $withColumns])
            ->when(
                filled($this->selectedBoardId),
                fn ($query) => $query->whereKey($this->selectedBoardId),
                fn ($query) => $query->where('is_default', true),
            )
            ->first()
            ?? TicketBoard::query()
                ->with(['columns' => $withColumns])
                ->first();

        $this->createTicketUrl = TicketResource::getUrl('create', [
            'ticket_board_id' => $this->board?->id,
        ]);
    }
}
