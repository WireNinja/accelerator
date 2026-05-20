@props([
    'file' => null,
    'line' => null,
    'class' => null,
    'function' => null,
    'snippet' => [],
])

@php
    $decodedSnippet = is_string($snippet) ? json_decode($snippet, true) : $snippet;
    $decodedSnippet = is_array($decodedSnippet) ? $decodedSnippet : [];
    $relativeFile = $file ? str_replace(base_path().'/', '', $file) : null;
@endphp

<section {{ $attributes->class(['overflow-hidden rounded-xl border border-white/10 bg-[#1d1d1d] shadow-2xl shadow-black/20']) }}>
    <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-white/[3%] px-4 py-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase text-neutral-500">Exception trace</p>
            <p class="mt-1 truncate font-mono text-sm text-neutral-200">
                {{ $relativeFile ?? 'unknown' }}@if($line):{{ $line }}@endif
            </p>
            @if($class || $function)
                <p class="mt-1 truncate font-mono text-xs text-neutral-500">
                    {{ trim(($class ? $class.'::' : '').($function ?? ''), ':') }}
                </p>
            @endif
        </div>
        <span class="rounded-md border border-emerald-400/20 bg-emerald-400/10 px-2 py-1 text-xs text-emerald-300">
            focused
        </span>
    </div>

    @if($decodedSnippet === [])
        <div class="px-4 py-8 text-center text-sm text-neutral-500">Source snippet unavailable.</div>
    @else
        <pre class="overflow-x-auto bg-[#202020] py-3 text-[13px] leading-7 text-neutral-300">@foreach($decodedSnippet as $sourceLine)<span class="block {{ ($sourceLine['highlight'] ?? false) ? 'bg-rose-700/70 text-white' : 'odd:bg-white/[2%]' }}"><span class="inline-block w-14 select-none px-3 text-right text-neutral-500">{{ $sourceLine['line'] ?? '' }}</span><code>{{ $sourceLine['code'] ?? '' }}</code></span>@endforeach</pre>
    @endif
</section>
