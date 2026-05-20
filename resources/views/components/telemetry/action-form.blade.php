@props([
    'action',
    'label',
    'variant' => 'secondary',
])

@php
    $classes = match ($variant) {
        'danger' => 'border-red-600 bg-red-600 text-white hover:bg-red-700',
        'success' => 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700',
        default => 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100',
    };
@endphp

<form method="POST" action="{{ $action }}" {{ $attributes->class(['inline-flex']) }}>
    @csrf
    <button type="submit" class="rounded-md border px-2.5 py-1.5 text-xs font-semibold {{ $classes }}">
        {{ $label }}
    </button>
</form>
