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

<section {{ $attributes->class(['rounded-lg border border-zinc-200 bg-white p-4']) }}>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h4 class="text-sm font-semibold text-zinc-950">Waterfall</h4>
            <p class="text-xs text-zinc-500">
                {{ $totalMs ? number_format((float) $totalMs, 2).'ms total request' : 'request duration unavailable' }}
                @if($dbQueryCount)
                    · {{ number_format((int) $dbQueryCount) }} DB queries
                    · {{ number_format((float) $dbDurationMs, 2) }}ms DB
                @endif
            </p>
        </div>
    </div>

    @if($decodedEvents === [])
        <div class="mt-4 rounded-md bg-zinc-50 px-3 py-6 text-center text-sm text-zinc-500">
            No timeline events captured for this occurrence.
        </div>
    @else
        <div class="mt-4 flex flex-col gap-2">
            @foreach($decodedEvents as $event)
                @php
                    $start = (float) ($event['start_ms'] ?? 0);
                    $duration = max(0.1, (float) ($event['duration_ms'] ?? 0));
                    $left = min(100, max(0, ($start / $maxEnd) * 100));
                    $width = min(100 - $left, max(1, ($duration / $maxEnd) * 100));
                @endphp
                <div class="grid gap-2 text-xs sm:grid-cols-[11rem_1fr_5rem] sm:items-center">
                    <div class="truncate font-mono text-zinc-600">{{ $event['connection'] ?? $event['name'] ?? 'event' }}</div>
                    <div class="h-7 rounded-md bg-zinc-100">
                        <div
                            class="h-7 rounded-md bg-sky-500"
                            style="margin-left: {{ $left }}%; width: {{ $width }}%;"
                            title="{{ $event['sql'] ?? $event['name'] ?? 'event' }}"
                        ></div>
                    </div>
                    <div class="font-mono text-zinc-600">{{ number_format($duration, 2) }}ms</div>
                    @if(isset($event['sql']))
                        <div class="truncate font-mono text-zinc-500 sm:col-start-2 sm:col-end-4">{{ $event['sql'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
