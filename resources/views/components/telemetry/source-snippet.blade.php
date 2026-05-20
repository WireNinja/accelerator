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
    $grammar = match ($extension) {
        'js', 'mjs', 'cjs' => \Phiki\Grammar\Grammar::Javascript,
        'ts' => \Phiki\Grammar\Grammar::Typescript,
        'css' => \Phiki\Grammar\Grammar::Css,
        'json' => \Phiki\Grammar\Grammar::Json,
        'vue' => \Phiki\Grammar\Grammar::Vue,
        default => \Phiki\Grammar\Grammar::Php,
    };
    $focusedIndex = $focusedLine > 0 ? $focusedLine - $startLine : -1;
    $highlightedCode = null;

    if ($decodedSnippet !== []) {
        $output = \Phiki\Adapters\Laravel\Facades\Phiki::codeToHtml($code, $grammar, \Phiki\Theme\Theme::NightOwl)
            ->withGutter()
            ->startingLine($startLine);

        if ($focusedIndex >= 0) {
            $output = $output->decoration(
                \Phiki\Transformers\Decorations\LineDecoration::forLine($focusedIndex)->class('focused-line'),
            );
        }

        $highlightedCode = $output->toString();
    }
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
        <div class="telemetry-code max-w-full overflow-x-auto bg-[#202020]">
            {!! $highlightedCode !!}
        </div>
    @endif
</section>
