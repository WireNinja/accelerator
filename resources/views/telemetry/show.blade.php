@extends('accelerator::telemetry.layout')

@section('title', class_basename($group['class']))

@php
    $focusedOccurrence = $occurrences->getCollection()->first();
    $method = $focusedOccurrence['method'] ?? null;
    $url = $focusedOccurrence['url'] ?? null;
    $headers = $focusedOccurrence && ($focusedOccurrence['request_headers'] ?? null)
        ? json_decode($focusedOccurrence['request_headers'], true)
        : [];
    $payload = $focusedOccurrence && ($focusedOccurrence['request_payload'] ?? null)
        ? json_decode($focusedOccurrence['request_payload'], true)
        : [];
    $relativeGroupFile = str_replace(base_path().'/', '', $group['file']);
    $sourceSnippet = $focusedOccurrence && ($focusedOccurrence['source_snippet'] ?? null)
        ? json_decode($focusedOccurrence['source_snippet'], true)
        : [];
    $timelineEvents = $focusedOccurrence && ($focusedOccurrence['timeline_events'] ?? null)
        ? json_decode($focusedOccurrence['timeline_events'], true)
        : [];
    $sourceSnippetText = is_array($sourceSnippet) && $sourceSnippet !== []
        ? collect($sourceSnippet)->map(function (array $sourceLine): string {
            $marker = ($sourceLine['highlight'] ?? false) ? '>' : ' ';

            return $marker.' '.str_pad((string) ($sourceLine['line'] ?? ''), 4, ' ', STR_PAD_LEFT).' | '.($sourceLine['code'] ?? '');
        })->implode(PHP_EOL)
        : null;
    $markdownSections = [
        '# '.class_basename($group['class']),
        '',
        '## Exception',
        '- Class: `'.$group['class'].'`',
        '- Message: '.($group['message'] ?? 'n/a'),
        '- File: `'.$relativeGroupFile.':'.$group['line'].'`',
        '- Status: '.$group['status'],
        '- Occurrences: '.number_format((int) $group['occurrence_count']),
        '- First seen: '.$group['first_seen_at'],
        '- Last seen: '.$group['last_seen_at'],
        '',
        '## Runtime',
        '- App: '.config('app.name'),
        '- Laravel: '.app()->version(),
        '- PHP: '.PHP_VERSION,
    ];

    if ($focusedOccurrence) {
        $markdownSections = array_merge($markdownSections, [
            '',
            '## Request',
            '- Method: '.($method ?? 'n/a'),
            '- URL: '.($url ?? 'n/a'),
            '- IP: '.($focusedOccurrence['ip'] ?? 'n/a'),
            '- User ID: '.($focusedOccurrence['user_id'] ?? 'guest'),
            '- Name: '.($focusedOccurrence['user_name'] ?? 'n/a'),
            '- Username: '.($focusedOccurrence['user_username'] ?? 'n/a'),
            '- Email: '.($focusedOccurrence['user_email'] ?? 'n/a'),
            '',
            '## Performance',
            '- Request duration: '.($focusedOccurrence['duration_ms'] !== null ? $focusedOccurrence['duration_ms'].'ms' : 'n/a'),
            '- DB queries: '.($focusedOccurrence['db_query_count'] ?? 'n/a'),
            '- DB duration: '.($focusedOccurrence['db_duration_ms'] !== null ? $focusedOccurrence['db_duration_ms'].'ms' : 'n/a'),
            '- Slowest query: '.($focusedOccurrence['slowest_query_ms'] !== null ? $focusedOccurrence['slowest_query_ms'].'ms' : 'n/a'),
            '- Peak memory: '.($focusedOccurrence['memory_usage_bytes'] ? number_format($focusedOccurrence['memory_usage_bytes'] / 1024 / 1024, 1).'MB' : 'n/a'),
        ]);

        if ($sourceSnippetText) {
            $markdownSections = array_merge($markdownSections, [
                '',
                '## Focused Source',
                '```php',
                $sourceSnippetText,
                '```',
            ]);
        }

        if (is_array($timelineEvents) && $timelineEvents !== []) {
            $markdownSections = array_merge($markdownSections, [
                '',
                '## Timeline',
                '```json',
                json_encode($timelineEvents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                '```',
            ]);
        }

        if (is_array($headers) && $headers !== []) {
            $markdownSections = array_merge($markdownSections, [
                '',
                '## Headers',
                '```json',
                json_encode($headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                '```',
            ]);
        }

        if (is_array($payload) && $payload !== []) {
            $markdownSections = array_merge($markdownSections, [
                '',
                '## Body',
                '```json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                '```',
            ]);
        }

        $markdownSections = array_merge($markdownSections, [
            '',
            '## Stack Trace',
            '```text',
            (string) ($focusedOccurrence['stack_trace'] ?? ''),
            '```',
        ]);
    }

    $markdown = implode(PHP_EOL, [
        ...$markdownSections,
    ]);
@endphp

@section('content')
<div class="flex flex-col gap-10">
    <section x-data="{ copied: false }" class="flex flex-col gap-12">
        <div class="flex items-start justify-between gap-4">
            <a href="{{ route('accelerator.telemetry.index') }}" class="text-sm text-neutral-500 hover:text-white">Open / {{ $group['id'] }}</a>
            <button
                type="button"
                x-on:click="navigator.clipboard.writeText(@js($markdown)); copied = true; setTimeout(() => copied = false, 1400)"
                class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-sm text-neutral-400 hover:bg-white/[8%] hover:text-white"
            >
                <span x-text="copied ? 'Copied' : 'Copy as Markdown'"></span>
            </button>
        </div>

        <div class="flex flex-col gap-5">
            <h1 class="text-3xl font-semibold tracking-tight text-white">{{ class_basename($group['class']) }}</h1>
            <p class="-mt-3 break-all font-mono text-xs text-neutral-500">{{ $relativeGroupFile }}:{{ $group['line'] }}</p>
            @if($group['message'] ?? null)
                <p class="text-xl font-light text-neutral-300">{{ $group['message'] }}</p>
            @endif

            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-md border border-white/10 bg-white/[3%] px-2 py-1 font-mono text-[13px] text-neutral-400">
                    LARAVEL <span class="text-neutral-200">{{ app()->version() }}</span>
                </span>
                <span class="rounded-md border border-white/10 bg-white/[3%] px-2 py-1 font-mono text-[13px] text-neutral-400">
                    PHP <span class="text-neutral-200">{{ PHP_VERSION }}</span>
                </span>
                <x-accelerator::telemetry.status-badge :status="$group['status']" />
                <span class="rounded-md border border-rose-400/40 bg-rose-500 px-2 py-1 font-mono text-[11px] font-semibold text-white">
                    CODE 0
                </span>
            </div>
        </div>

        @if($url)
            <div class="flex min-w-0 items-center gap-2 rounded-lg border border-white/10 bg-white/[3%] px-3 py-2">
                <span class="rounded-md bg-rose-500 px-2 py-1 font-mono text-xs font-semibold text-white">500</span>
                @if($method)
                    <span class="rounded-md bg-white/10 px-2 py-1 font-mono text-xs text-neutral-200">{{ $method }}</span>
                @endif
                <span class="min-w-0 truncate font-mono text-sm text-neutral-300">{{ $url }}</span>
            </div>
        @endif

        <section class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border border-white/10 bg-[#1d1d1d]">
                <div class="border-b border-white/10 bg-white/[4%] px-4 py-3 text-sm font-medium text-white">Manage</div>
                <div class="grid gap-4 p-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div class="flex justify-between gap-4 sm:block">
                        <span class="text-neutral-500">Status</span>
                        <div class="mt-1"><x-accelerator::telemetry.status-badge :status="$group['status']" /></div>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <span class="text-neutral-500">Occurrences</span>
                        <div class="mt-1 font-mono text-neutral-200">{{ number_format($group['occurrence_count']) }}</div>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <span class="text-neutral-500">First seen</span>
                        <div class="mt-1 font-mono text-neutral-200">{{ $group['first_seen_at'] }}</div>
                    </div>
                    <div class="flex justify-between gap-4 sm:block">
                        <span class="text-neutral-500">Last seen</span>
                        <div class="mt-1 font-mono text-neutral-200">{{ $group['last_seen_at'] }}</div>
                    </div>
                </div>
                <div class="border-t border-white/10 p-4">
                    <x-accelerator::telemetry.group-actions :group="$group" />
                </div>
            </section>

            @if($focusedOccurrence)
                <section class="rounded-xl border border-white/10 bg-[#1d1d1d] p-4">
                    <p class="mb-3 text-xs font-semibold uppercase text-neutral-500">Latest Actor</p>
                    <x-accelerator::telemetry.user-chip
                        :user-id="$focusedOccurrence['user_id']"
                        :name="$focusedOccurrence['user_name'] ?? null"
                        :username="$focusedOccurrence['user_username'] ?? null"
                        :email="$focusedOccurrence['user_email'] ?? null"
                    />
                </section>
            @endif
        </section>
    </section>

    @if($focusedOccurrence)
        <x-accelerator::telemetry.source-snippet
            :file="$focusedOccurrence['source_file'] ?? $group['file']"
            :line="$focusedOccurrence['source_line'] ?? $group['line']"
            :class="$focusedOccurrence['source_class'] ?? null"
            :function="$focusedOccurrence['source_function'] ?? null"
            :snippet="$focusedOccurrence['source_snippet'] ?? []"
        />

        <x-accelerator::telemetry.waterfall
            :events="$focusedOccurrence['timeline_events'] ?? []"
            :total-ms="$focusedOccurrence['duration_ms']"
            :db-query-count="$focusedOccurrence['db_query_count'] ?? null"
            :db-duration-ms="$focusedOccurrence['db_duration_ms'] ?? null"
        />

        <section class="min-w-0">
            <div class="flex min-w-0 flex-col gap-8">
                <section>
                    <h2 class="mb-4 text-lg font-semibold text-white">Headers</h2>
                    @if(is_array($headers) && $headers !== [])
                        <div class="flex flex-col gap-3 font-mono text-sm">
                            @foreach($headers as $key => $value)
                                <div class="grid gap-2 sm:grid-cols-[12rem_1fr]">
                                    <div class="uppercase text-neutral-500">{{ str_replace('_', '-', $key) }}</div>
                                    <div class="truncate text-neutral-200">{{ is_array($value) ? implode(', ', $value) : $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-white/10 bg-white/[3%] px-4 py-5 text-center font-mono text-sm uppercase text-neutral-500">// no headers captured</div>
                    @endif
                </section>

                <section>
                    <h2 class="mb-4 text-lg font-semibold text-white">Body</h2>
                    <pre class="overflow-x-auto rounded-lg border border-white/10 bg-white/[3%] p-5 text-sm text-neutral-400">@if(is_array($payload) && $payload !== [])
{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}
@else
// NO REQUEST BODY
@endif</pre>
                </section>

                <section>
                    <h2 class="mb-4 text-lg font-semibold text-white">Recent occurrences</h2>
                    <div class="flex min-w-0 flex-col gap-3">
                        @foreach($occurrences as $occurrence)
                            <x-accelerator::telemetry.occurrence-card :occurrence="$occurrence" />
                        @endforeach
                    </div>
                </section>
            </div>
        </section>
    @endif

    @if($occurrences->hasPages())
        <div class="flex items-center justify-center gap-2 text-sm">
            @if($occurrences->onFirstPage())
                <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-600">Prev</span>
            @else
                <a href="{{ $occurrences->previousPageUrl() }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Prev</a>
            @endif

            <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-500">
                Page {{ $occurrences->currentPage() }} of {{ $occurrences->lastPage() }}
            </span>

            @if($occurrences->hasMorePages())
                <a href="{{ $occurrences->nextPageUrl() }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Next</a>
            @else
                <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-600">Next</span>
            @endif
        </div>
    @endif
</div>
@endsection
