@props(['occurrence'])

<article
    x-data="{ open: false, headers: false, payload: false }"
    class="rounded-lg border border-zinc-200 bg-white"
>
    <button type="button" x-on:click="open = ! open" class="flex w-full flex-col gap-3 px-4 py-3 text-left hover:bg-zinc-50 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-semibold text-zinc-900">{{ $occurrence['created_at'] }}</span>
                @if($occurrence['user_id'])
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600">User #{{ $occurrence['user_id'] }}</span>
                @endif
            </div>
            <p class="mt-1 truncate text-sm font-medium text-zinc-900">{{ $occurrence['message'] }}</p>
            <p class="mt-1 truncate font-mono text-xs text-zinc-500">{{ $occurrence['method'] }} {{ $occurrence['url'] }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2 text-xs text-zinc-500">
            @if($occurrence['duration_ms'])
                <span>{{ $occurrence['duration_ms'] }}ms</span>
            @endif
            @if($occurrence['memory_usage_bytes'])
                <span>{{ number_format($occurrence['memory_usage_bytes'] / 1024 / 1024, 1) }}MB</span>
            @endif
            <span x-text="open ? 'Collapse' : 'Expand'" class="rounded-md border border-zinc-200 px-2 py-1 text-zinc-700"></span>
        </div>
    </button>

    <div x-cloak x-show="open" class="border-t border-zinc-200 px-4 py-4">
        <pre class="overflow-x-auto whitespace-pre-wrap break-words rounded-md bg-zinc-950 p-4 text-xs leading-6 text-zinc-100">{{ $occurrence['stack_trace'] }}</pre>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @if($occurrence['request_headers'])
                <section>
                    <button type="button" x-on:click="headers = ! headers" class="text-xs font-semibold text-zinc-700 hover:text-zinc-950">
                        Request Headers
                    </button>
                    <pre x-cloak x-show="headers" class="mt-2 overflow-x-auto whitespace-pre-wrap break-words rounded-md bg-zinc-100 p-3 text-xs leading-5 text-zinc-800">{{ json_encode(json_decode($occurrence['request_headers'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif

            @if($occurrence['request_payload'])
                <section>
                    <button type="button" x-on:click="payload = ! payload" class="text-xs font-semibold text-zinc-700 hover:text-zinc-950">
                        Request Payload
                    </button>
                    <pre x-cloak x-show="payload" class="mt-2 overflow-x-auto whitespace-pre-wrap break-words rounded-md bg-zinc-100 p-3 text-xs leading-5 text-zinc-800">{{ json_encode(json_decode($occurrence['request_payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </section>
            @endif
        </div>

        <p class="mt-4 text-xs text-zinc-500">IP: {{ $occurrence['ip'] ?? 'unknown' }}</p>
    </div>
</article>
