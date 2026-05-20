@props(['status'])

@php
    $classes = match ($status) {
        'open' => 'border-red-200 bg-red-50 text-red-700',
        'resolved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'muted' => 'border-zinc-200 bg-zinc-100 text-zinc-600',
        default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold uppercase', $classes]) }}>
    {{ $status }}
</span>
