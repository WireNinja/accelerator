@php
use Illuminate\Support\Carbon;
@endphp

<div class="space-y-3">
    @forelse ($entries as $entry)
    @php
    $badgeColorClass = match ($entry['author_badge_color'] ?? 'gray') {
    'info' => 'bg-info-50 text-info-700',
    'success' => 'bg-success-50 text-success-700',
    'warning' => 'bg-warning-50 text-warning-700',
    'danger' => 'bg-danger-50 text-danger-700',
    default => 'bg-gray-100 text-gray-700',
    };
    @endphp

    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600">
                <x-filament::icon :icon="$entry['icon']" class="h-4 w-4" />
            </div>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="text-sm font-semibold text-gray-900">{{ $entry['title'] }}</h4>

                    @if (filled($entry['author_badge'] ?? null))
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badgeColorClass }}">
                        {{ $entry['author_badge'] }}
                    </span>
                    @endif
                </div>

                @if (filled($entry['description'] ?? null))
                <p class="text-sm leading-6 text-gray-600 whitespace-pre-line">{{ $entry['description'] }}</p>
                @endif

                <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <span>{{ $entry['author'] }}</span>
                    <span>•</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($entry['occurred_at'])->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </article>
    @empty
    <x-filament::callout icon="lucide-activity" color="gray">
        <x-slot name="heading">
            Timeline belum ada
        </x-slot>

        <x-slot name="description">
            Begitu tiket ini berubah status, dipindah kolom, atau mendapat komentar, riwayatnya akan muncul di sini.
        </x-slot>
    </x-filament::callout>
    @endforelse
</div>