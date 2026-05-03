<x-filament-panels::page>
    @if ($this->board === null)
    <x-filament::callout
        icon="heroicon-o-information-circle"
        color="warning">
        <x-slot name="heading">
            Antrian Tiket Belum Tersedia
        </x-slot>

        <x-slot name="description">
            Belum ada board tiket. Buat board dan kolom workflow dulu supaya request client bisa langsung masuk antrian.
        </x-slot>

        <x-slot name="footer">
            <x-filament::button
                href="{{ $this->createBoardUrl }}"
                tag="a"
                size="sm">
                Buat Antrian
            </x-filament::button>
        </x-slot>
    </x-filament::callout>
    @else

    <div class="space-y-4">
        <x-filament::callout
            icon="heroicon-o-shield-check"
            color="info">
            <x-slot name="heading">
                Ringkasan Akses
            </x-slot>

            <x-slot name="description">
                {{ $this->visibilityInfo }}
            </x-slot>
        </x-filament::callout>

        <x-filament::section icon="heroicon-o-clipboard-document-list">
            <x-slot name="heading">
                {{ $this->board->name }}
            </x-slot>

            <x-slot name="description">
                {{ $this->board->description }}
            </x-slot>

            <x-slot name="afterHeader">
                <div class="flex items-center gap-2">
                    <x-filament::button
                        href="{{ $this->manageTicketsUrl }}"
                        tag="a"
                        color="gray"
                        icon="lucide-list">
                        Daftar Tiket
                    </x-filament::button>
                    @if ($this->isPetugas)
                    <x-filament::button
                        href="{{ $this->manageBoardsUrl }}"
                        tag="a"
                        color="gray"
                        icon="lucide-settings">
                        Kelola Board
                    </x-filament::button>
                    @endif
                    <x-filament::button
                        href="{{ $this->createTicketUrl }}"
                        tag="a"
                        icon="lucide-plus">
                        Buat Tiket
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="text-sm">
                    <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Pilih Board</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedBoardId">
                            @foreach ($this->boardOptions as $boardId => $boardName)
                            <option value="{{ $boardId }}">{{ $boardName }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>

                <label class="text-sm">
                    <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Cari Tiket</span>
                    <x-filament::input.wrapper icon="lucide-search">
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.500ms="search"
                            placeholder="Cari no tiket, judul, pelapor..."
                        />
                    </x-filament::input.wrapper>
                </label>
            </div>

            <div x-data="{ showFilters: false }" class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                <button type="button" @click="showFilters = !showFilters" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    <span x-show="!showFilters" class="flex items-center gap-1"><x-filament::icon icon="lucide-filter" class="h-4 w-4"/> Tampilkan Filter Lanjutan</span>
                    <span x-show="showFilters" x-cloak class="flex items-center gap-1"><x-filament::icon icon="lucide-x" class="h-4 w-4"/> Sembunyikan Filter Lanjutan</span>
                </button>

                <div x-show="showFilters" x-cloak x-collapse class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <label class="text-sm">
                        <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Tipe Tiket</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filterType">
                                <option value="">Semua Tipe</option>
                                @foreach (\WireNinja\Accelerator\Enums\Ticket\TicketTypeEnum::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    <label class="text-sm">
                        <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Prioritas</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filterPriority">
                                <option value="">Semua Prioritas</option>
                                @foreach (\WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::cases() as $priority)
                                    <option value="{{ $priority->value }}">{{ $priority->getLabel() }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    <label class="text-sm">
                        <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Modul Terkait</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filterModulTerkait">
                                <option value="">Semua Modul</option>
                                <option value="inventory">Inventory</option>
                                <option value="finance">Finance</option>
                                <option value="hr">HR</option>
                                <option value="sales">Sales</option>
                                <option value="purchasing">Purchasing</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    <label class="text-sm">
                        <span class="mb-1 block font-medium text-gray-700 dark:text-gray-300">Dampak Bisnis</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filterDampakBisnis">
                                <option value="">Semua Dampak</option>
                                <option value="rendah">Rendah</option>
                                <option value="sedang">Sedang</option>
                                <option value="tinggi">Tinggi</option>
                                <option value="kritis">Kritis</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                </div>
            </div>
        </x-filament::section>

        @php
        $allTickets = $this->board->columns->flatMap(fn ($col) => $col->tickets);
        $totalOpen = $allTickets->whereNotIn('status', [\WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Resolved, \WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Closed])->count();
        $totalOverdue = $allTickets->filter(fn ($t) => filled($t->due_at) && $t->due_at->isPast() && ! in_array($t->status, [\WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Resolved, \WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Closed]))->count();
        $totalUnassigned = $allTickets->whereNull('assignee_id')->whereNotIn('status', [\WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Resolved, \WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Closed])->count();
        @endphp

        @if ($this->isPetugas)
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tiket Aktif</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $totalOpen }}</p>
            </div>
            <div class="rounded-lg border px-4 py-3 {{ $totalUnassigned > 0 ? 'border-warning-300 bg-warning-50 dark:border-warning-600 dark:bg-warning-900/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum Ditugaskan</p>
                <p class="mt-1 text-xl font-bold {{ $totalUnassigned > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-900 dark:text-white' }}">{{ $totalUnassigned }}</p>
            </div>
            <div class="rounded-lg border px-4 py-3 {{ $totalOverdue > 0 ? 'border-danger-300 bg-danger-50 dark:border-danger-600 dark:bg-danger-900/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Melewati Tenggat</p>
                <p class="mt-1 text-xl font-bold {{ $totalOverdue > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $totalOverdue }}</p>
            </div>
        </div>
        @endif

        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach ($this->board->columns as $column)
            <section class="w-80 flex-shrink-0 rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <header class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $column->name }}</h3>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $column->tickets->count() }}
                        </span>
                    </div>
                </header>

                <div class="space-y-3 p-3" style="max-height: 70vh; overflow-y: auto;">
                    @forelse ($column->tickets as $ticket)
                    @php
                    $isOverdue = filled($ticket->due_at) && $ticket->due_at->isPast() && ! in_array($ticket->status, [\WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Resolved, \WireNinja\Accelerator\Enums\Ticket\TicketStatusEnum::Closed]);
                    $priorityBorder = match ($ticket->priority) {
                    \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::Urgent => 'border-l-danger-500',
                    \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::High => 'border-l-warning-500',
                    \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::Medium => 'border-l-info-500',
                    default => 'border-l-gray-300 dark:border-l-gray-600',
                    };
                    @endphp
                    <a href="{{ $this->getTicketViewUrl($ticket->id) }}"
                        class="block rounded-lg border border-gray-200 border-l-4 {{ $priorityBorder }} bg-gray-50 p-3 transition hover:shadow-md hover:bg-white dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800 {{ $isOverdue ? 'ring-1 ring-danger-300 dark:ring-danger-600' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $ticket->ticket_number }}</p>
                                <h4 class="mt-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100" title="{{ $ticket->title }}">{{ $ticket->title }}</h4>
                            </div>
                            <span @class([ 'shrink-0 rounded px-2 py-1 text-xs font-semibold border' , 'bg-danger-50 text-danger-700 border-danger-200 dark:bg-danger-900/30 dark:text-danger-400 dark:border-danger-700'=> $ticket->priority === \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::Urgent,
                                'bg-warning-50 text-warning-700 border-warning-200 dark:bg-warning-900/30 dark:text-warning-400 dark:border-warning-700' => $ticket->priority === \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::High,
                                'bg-info-50 text-info-700 border-info-200 dark:bg-info-900/30 dark:text-info-400 dark:border-info-700' => $ticket->priority === \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::Medium,
                                'bg-white text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600' => $ticket->priority === \WireNinja\Accelerator\Enums\Ticket\TicketPriorityEnum::Low,
                                ])>
                                {{ $ticket->priority->getLabel() }}
                            </span>
                        </div>

                        @if ($isOverdue)
                        <div class="mt-2 flex items-center gap-1 text-xs font-medium text-danger-600 dark:text-danger-400">
                            <x-filament::icon icon="lucide-alert-triangle" class="h-3 w-3" />
                            Melewati tenggat {{ $ticket->due_at->diffForHumans() }}
                        </div>
                        @elseif (filled($ticket->due_at))
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Tenggat {{ $ticket->due_at->diffForHumans() }}
                        </div>
                        @endif

                        <div class="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>
                                {{ $ticket->last_activity_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}
                            </span>
                            <span>
                                {{ $ticket->status->getLabel() }}
                            </span>
                        </div>

                        @if ($ticket->labels->isNotEmpty() || $ticket->is_public || $ticket->watchers->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1">
                            @if ($ticket->is_public)
                            <span class="rounded bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/30 dark:text-success-400">
                                Publik
                            </span>
                            @endif
                            @if ($ticket->watchers->isNotEmpty())
                            <span class="rounded bg-info-100 px-2 py-0.5 text-xs font-medium text-info-700 dark:bg-info-900/30 dark:text-info-400" title="CC: {{ $ticket->watchers->pluck('name')->join(', ') }}">
                                CC: {{ $ticket->watchers->count() }}
                            </span>
                            @endif
                            @foreach ($ticket->labels as $label)
                            <span class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ $label->name }}
                            </span>
                            @endforeach
                        </div>
                        @endif

                        <div class="mt-2 flex items-center justify-between border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <div class="flex items-center gap-3">
                                <span class="flex items-center gap-1" title="Task">
                                    <x-filament::icon icon="lucide-check-square" class="h-3 w-3" />
                                    {{ $ticket->done_tasks }}/{{ $ticket->total_tasks }}
                                </span>
                                @if ($ticket->comments_count > 0)
                                <span class="flex items-center gap-1" title="Komentar">
                                    <x-filament::icon icon="lucide-message-square" class="h-3 w-3" />
                                    {{ $ticket->comments_count }}
                                </span>
                                @endif
                            </div>
                            <span title="{{ $ticket->assigneeUser?->name ?? 'Belum ditugaskan' }}">
                                {{ $ticket->assigneeUser?->name ?? 'Belum ditugaskan' }}
                            </span>
                        </div>
                    </a>
                    @empty
                    <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-500">
                        Belum ada tiket
                    </div>
                    @endforelse
                </div>
            </section>
            @endforeach
        </div>
    </div>
    @endif
</x-filament-panels::page>
