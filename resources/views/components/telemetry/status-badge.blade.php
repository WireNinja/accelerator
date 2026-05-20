@props(['status'])

@php
    $classes = match ($status) {
        'open' => 'border-rose-400/40 bg-rose-500 text-white',
        'resolved' => 'border-emerald-400/30 bg-emerald-500/15 text-emerald-300',
        'muted' => 'border-neutral-600 bg-neutral-800 text-neutral-300',
        default => 'border-neutral-600 bg-neutral-900 text-neutral-300',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-md border px-2 py-1 font-mono text-[11px] font-semibold uppercase', $classes]) }}>
    {{ $status }}
</span>
