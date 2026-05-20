@props([
    'name' => null,
    'username' => null,
    'email' => null,
    'userId' => null,
])

@php
    $label = $name ?: ($username ? '@'.$username : ($email ?: ($userId ? 'User #'.$userId : 'Guest')));
    $meta = collect([$username ? '@'.$username : null, $email])->filter()->implode(' · ');
@endphp

<div {{ $attributes->class(['flex min-w-0 items-center gap-2']) }}>
    <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-700">
        {{ mb_strtoupper(mb_substr($label, 0, 1)) }}
    </div>
    <div class="min-w-0">
        <p class="truncate text-sm font-medium text-zinc-900">{{ $label }}</p>
        @if($meta !== '')
            <p class="truncate text-xs text-zinc-500">{{ $meta }}</p>
        @endif
    </div>
</div>
