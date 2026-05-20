@props(['occurrence'])

<article
    x-data="{ open: false, headers: false, payload: false }"
    class="rounded-xl border border-white/10 bg-[#1d1d1d]"
>
    <button type="button" x-on:click="open = ! open" class="flex w-full flex-col gap-3 px-4 py-3 text-left hover:bg-white/[3%] sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-semibold text-neutral-200">{{ $occurrence['created_at'] }}</span>
                @if($occurrence['user_id'] || ($occurrence['user_name'] ?? null) || ($occurrence['user_email'] ?? null))
                    <span class="rounded-full bg-white/[6%] px-2 py-0.5 text-xs text-neutral-400">
                        {{ $occurrence['user_name'] ?? $occurrence['user_email'] ?? 'User #'.$occurrence['user_id'] }}
                    </span>
                @endif
            </div>
            <p class="mt-1 truncate text-sm font-medium text-neutral-100">{{ $occurrence['message'] }}</p>
            <p class="mt-1 truncate font-mono text-xs text-neutral-500">{{ $occurrence['method'] }} {{ $occurrence['url'] }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2 text-xs text-neutral-500">
            @if($occurrence['duration_ms'])
                <span>{{ $occurrence['duration_ms'] }}ms</span>
            @endif
            @if($occurrence['memory_usage_bytes'])
                <span>{{ number_format($occurrence['memory_usage_bytes'] / 1024 / 1024, 1) }}MB</span>
            @endif
            <span x-text="open ? 'Collapse' : 'Expand'" class="rounded-md border border-white/10 bg-white/[3%] px-2 py-1 text-neutral-300"></span>
        </div>
    </button>

    <div x-cloak x-show="open" class="border-t border-white/10 px-4 py-4">
        <div class="mb-4 grid gap-4 lg:grid-cols-[18rem_1fr]">
            <div class="rounded-xl border border-white/10 bg-white/[3%] p-3">
                <p class="mb-2 text-xs font-semibold uppercase text-neutral-500">Actor</p>
                <x-accelerator::telemetry.user-chip
                    :user-id="$occurrence['user_id']"
                    :name="$occurrence['user_name'] ?? null"
                    :username="$occurrence['user_username'] ?? null"
                    :email="$occurrence['user_email'] ?? null"
                />
            </div>

            <x-accelerator::telemetry.waterfall
                :events="$occurrence['timeline_events'] ?? []"
                :total-ms="$occurrence['duration_ms']"
                :db-query-count="$occurrence['db_query_count'] ?? null"
                :db-duration-ms="$occurrence['db_duration_ms'] ?? null"
            />
        </div>

        <x-accelerator::telemetry.source-snippet
            class="mb-4"
            :file="$occurrence['source_file'] ?? null"
            :line="$occurrence['source_line'] ?? null"
            :class="$occurrence['source_class'] ?? null"
            :function="$occurrence['source_function'] ?? null"
            :snippet="$occurrence['source_snippet'] ?? []"
        />

        <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-xl border border-white/10 bg-[#202020] p-4 text-xs leading-6 text-neutral-300">{{ $occurrence['stack_trace'] }}</pre>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @if($occurrence['request_headers'])
                <section>
                    <button type="button" x-on:click="headers = ! headers" class="text-xs font-semibold text-neutral-400 hover:text-white">
                        Headers
                    </button>
                    <pre x-cloak x-show="headers" class="mt-2 overflow-x-auto whitespace-pre-wrap break-words rounded-xl border border-white/10 bg-[#202020] p-3 text-xs leading-5 text-neutral-300">{{ json_encode(json_decode($occurrence['request_headers'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif

            @if($occurrence['request_payload'])
                <section>
                    <button type="button" x-on:click="payload = ! payload" class="text-xs font-semibold text-neutral-400 hover:text-white">
                        Body
                    </button>
                    <pre x-cloak x-show="payload" class="mt-2 overflow-x-auto whitespace-pre-wrap break-words rounded-xl border border-white/10 bg-[#202020] p-3 text-xs leading-5 text-neutral-300">{{ json_encode(json_decode($occurrence['request_payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif
        </div>

        <p class="mt-4 text-xs text-neutral-500">IP: {{ $occurrence['ip'] ?? 'unknown' }}</p>
    </div>
</article>
