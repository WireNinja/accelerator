@props(['entry'])

@php
    $levelClasses = match ($entry['level']) {
        'error', 'critical', 'alert', 'emergency' => 'border-red-200 bg-red-50 text-red-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-700',
        'info', 'notice' => 'border-sky-200 bg-sky-50 text-sky-700',
        default => 'border-zinc-200 bg-zinc-100 text-zinc-700',
    };
@endphp

<article x-data="{ open: false }" class="rounded-lg border border-zinc-200 bg-white">
    <button type="button" x-on:click="open = ! open" class="flex w-full flex-col gap-3 px-4 py-3 text-left hover:bg-zinc-50 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                @if($entry['timestamp'])
                    <span class="font-mono text-xs font-semibold text-zinc-900">{{ $entry['timestamp'] }}</span>
                @endif
                @if($entry['level'])
                    <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold uppercase {{ $levelClasses }}">{{ $entry['level'] }}</span>
                @endif
                <span class="text-xs text-zinc-500">{{ $entry['line_count'] }} lines</span>
            </div>
            <p class="mt-1 break-words text-sm text-zinc-800">{{ $entry['summary'] }}</p>
        </div>
        <span x-text="open ? 'Collapse' : 'Expand'" class="shrink-0 rounded-md border border-zinc-200 px-2 py-1 text-xs font-semibold text-zinc-700"></span>
    </button>

    <pre x-cloak x-show="open" class="overflow-x-auto whitespace-pre-wrap break-words border-t border-zinc-200 bg-zinc-950 p-4 text-xs leading-6 text-zinc-100">{{ $entry['body'] }}</pre>
</article>
