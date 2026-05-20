@props([
    'events' => [],
    'totalMs' => null,
    'dbQueryCount' => null,
    'dbDurationMs' => null,
])

@php
    $decodedEvents = is_string($events) ? json_decode($events, true) : $events;
    $decodedEvents = is_array($decodedEvents) ? array_values(array_filter($decodedEvents, 'is_array')) : [];
    $maxEnd = (float) ($totalMs ?: 0);

    foreach ($decodedEvents as $event) {
        $maxEnd = max($maxEnd, (float) ($event['start_ms'] ?? 0) + (float) ($event['duration_ms'] ?? 0));
    }

    $maxEnd = max($maxEnd, 1);
@endphp

<section {{ $attributes->class(['min-w-0 overflow-hidden rounded-xl border border-white/10 bg-[#1d1d1d] p-4']) }}>
    <div class="flex items-center justify-between gap-4">
        <div>
            <h4 class="text-base font-semibold text-neutral-100">Queries</h4>
            <p class="text-xs text-neutral-500">
                {{ $totalMs ? number_format((float) $totalMs, 2).'ms request' : 'request duration unavailable' }}
                @if($dbQueryCount)
                    · {{ number_format((int) $dbQueryCount) }} queries
                    · {{ number_format((float) $dbDurationMs, 2) }}ms DB
                @endif
            </p>
        </div>
        @if($decodedEvents !== [])
            <span class="font-mono text-xs text-neutral-500">1-{{ count($decodedEvents) }} of {{ count($decodedEvents) }}</span>
        @endif
    </div>

    @if($decodedEvents === [])
        <div class="mt-4 rounded-lg border border-white/5 bg-white/[3%] px-3 py-6 text-center text-sm text-neutral-500">
            No query timeline captured.
        </div>
    @else
        <div class="mt-4 flex flex-col gap-1.5">
            @foreach($decodedEvents as $event)
                @php
                    $start = (float) ($event['start_ms'] ?? 0);
                    $duration = max(0.1, (float) ($event['duration_ms'] ?? 0));
                    $left = min(100, max(0, ($start / $maxEnd) * 100));
                    $width = min(100 - $left, max(1, ($duration / $maxEnd) * 100));
                @endphp
                <div class="min-w-0 rounded-md bg-white/[4%] px-3 py-2">
                    <div class="flex items-center gap-3 text-xs">
                        <span class="shrink-0 font-mono text-neutral-500">{{ $event['connection'] ?? 'db' }}</span>
                        <span class="min-w-0 flex-1 truncate font-mono text-neutral-300">{{ $event['sql'] ?? $event['name'] ?? 'query' }}</span>
                        <span class="shrink-0 font-mono text-neutral-200">{{ number_format($duration, 2) }}ms</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-black/40">
                        <div class="h-1.5 rounded-full bg-emerald-400" style="margin-left: {{ $left }}%; width: {{ $width }}%;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
