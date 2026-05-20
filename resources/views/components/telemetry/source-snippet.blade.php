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
    $code = collect($decodedSnippet)->map(fn (array $sourceLine): string => (string) ($sourceLine['code'] ?? ''))->implode(PHP_EOL);
    $startLine = (int) ($decodedSnippet[0]['line'] ?? $line ?? 1);
    $focusedLine = (int) ($line ?? 0);
    $extension = $relativeFile ? strtolower(pathinfo($relativeFile, PATHINFO_EXTENSION)) : 'php';
    $language = match ($extension) {
        'js', 'mjs', 'cjs' => 'js',
        'ts' => 'ts',
        'css' => 'css',
        'json' => 'json',
        'vue' => 'vue',
        default => 'php',
    };
@endphp

<section {{ $attributes->class(['max-w-full overflow-hidden rounded-xl border border-white/10 bg-[#1d1d1d] shadow-2xl shadow-black/20']) }}>
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
        <div
            data-shiki-snippet
            data-code="{{ e($code) }}"
            data-lang="{{ $language }}"
            data-start-line="{{ $startLine }}"
            data-focused-line="{{ $focusedLine }}"
            class="max-w-full overflow-x-auto bg-[#202020] font-mono text-[13px] leading-7"
        >
            <pre class="py-3 text-neutral-300">@foreach($decodedSnippet as $sourceLine)<span class="block {{ ($sourceLine['highlight'] ?? false) ? 'bg-rose-700/70 text-white' : 'odd:bg-white/[2%]' }}"><span class="inline-block w-14 select-none px-3 text-right text-neutral-500">{{ $sourceLine['line'] ?? '' }}</span><code>{{ $sourceLine['code'] ?? '' }}</code></span>@endforeach</pre>
        </div>
    @endif
</section>

@pushOnce('scripts', 'accelerator-telemetry-shiki')
    <script type="module">
        import { codeToHtml } from 'https://esm.sh/shiki@3.0.0'

        const renderShikiSnippets = async () => {
            const snippets = document.querySelectorAll('[data-shiki-snippet]:not([data-shiki-rendered])')

            for (const snippet of snippets) {
                snippet.dataset.shikiRendered = 'true'

                try {
                    const startLine = Number.parseInt(snippet.dataset.startLine || '1', 10)
                    const focusedLine = Number.parseInt(snippet.dataset.focusedLine || '0', 10)
                    const html = await codeToHtml(snippet.dataset.code || '', {
                        lang: snippet.dataset.lang || 'php',
                        theme: 'night-owl',
                    })

                    snippet.innerHTML = html

                    snippet.querySelectorAll('.line').forEach((line, index) => {
                        const lineNumber = startLine + index
                        line.dataset.line = String(lineNumber)

                        if (lineNumber === focusedLine) {
                            line.classList.add('is-focused')
                        }
                    })
                } catch (error) {
                    snippet.removeAttribute('data-shiki-rendered')
                    console.warn('[Telemetry] Shiki render failed.', error)
                }
            }
        }

        renderShikiSnippets()
        document.addEventListener('alpine:init', renderShikiSnippets)
    </script>
@endPushOnce
