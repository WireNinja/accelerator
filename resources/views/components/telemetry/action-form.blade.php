@props([
    'action',
    'label',
    'variant' => 'secondary',
])

@php
    $classes = match ($variant) {
        'danger' => 'border-rose-400/40 bg-rose-500 text-white hover:bg-rose-400',
        'success' => 'border-emerald-400/30 bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25',
        default => 'border-white/10 bg-white/[3%] text-neutral-300 hover:bg-white/[8%] hover:text-white',
    };
@endphp

<form method="POST" action="{{ $action }}" {{ $attributes->class(['inline-flex']) }}>
    @csrf
    <button type="submit" class="rounded-md border px-2.5 py-1.5 text-xs font-medium {{ $classes }}">
        {{ $label }}
    </button>
</form>
