@props(['entry'])

@php
    $levelClasses = match ($entry['level']) {
        'error', 'critical', 'alert', 'emergency' => 'border-rose-400/40 bg-rose-500 text-white',
        'warning' => 'border-amber-400/30 bg-amber-400/10 text-amber-300',
        'info', 'notice' => 'border-sky-400/30 bg-sky-400/10 text-sky-300',
        default => 'border-white/10 bg-white/[3%] text-neutral-300',
    };
@endphp

<article x-data="{ open: false }" class="rounded-xl border border-white/10 bg-[#1d1d1d]">
    <button type="button" x-on:click="open = ! open" class="flex w-full flex-col gap-3 px-4 py-3 text-left hover:bg-white/[3%] lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                @if($entry['timestamp'])
                    <span class="font-mono text-xs font-semibold text-neutral-200">{{ $entry['timestamp'] }}</span>
                @endif
                @if($entry['level'])
                    <span class="inline-flex rounded-md border px-2 py-0.5 font-mono text-[11px] font-semibold uppercase {{ $levelClasses }}">{{ $entry['level'] }}</span>
                @endif
                <span class="text-xs text-neutral-500">{{ $entry['line_count'] }} lines</span>
            </div>
            <p class="mt-1 break-words text-sm text-neutral-300">{{ $entry['summary'] }}</p>
        </div>
        <span x-text="open ? 'Collapse' : 'Expand'" class="shrink-0 rounded-md border border-white/10 bg-white/[3%] px-2 py-1 text-xs font-medium text-neutral-300"></span>
    </button>

    <pre x-cloak x-show="open" class="overflow-x-auto whitespace-pre-wrap break-words border-t border-white/10 bg-[#202020] p-4 text-xs leading-6 text-neutral-300">{{ $entry['body'] }}</pre>
</article>
