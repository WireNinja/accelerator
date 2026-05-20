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

<section {{ $attributes->class(['rounded-lg border border-zinc-200 bg-white']) }}>
    <div class="border-b border-zinc-200 px-4 py-3">
        <p class="text-xs font-semibold uppercase text-zinc-500">Focused Source</p>
        <p class="mt-1 break-all font-mono text-sm text-zinc-900">
            {{ $relativeFile ?? 'unknown' }}@if($line):{{ $line }}@endif
        </p>
        @if($class || $function)
            <p class="mt-1 truncate font-mono text-xs text-zinc-500">
                {{ trim(($class ? $class.'::' : '').($function ?? ''), ':') }}
            </p>
        @endif
    </div>

    @if($decodedSnippet === [])
        <div class="px-4 py-6 text-sm text-zinc-500">Source snippet unavailable.</div>
    @else
        <pre class="overflow-x-auto bg-zinc-950 py-3 text-xs leading-6 text-zinc-100">@foreach($decodedSnippet as $sourceLine)<span class="block {{ ($sourceLine['highlight'] ?? false) ? 'bg-red-950/70 text-white' : '' }}"><span class="inline-block w-12 select-none px-3 text-right text-zinc-500">{{ $sourceLine['line'] ?? '' }}</span><code>{{ $sourceLine['code'] ?? '' }}</code></span>@endforeach</pre>
    @endif
</section>
