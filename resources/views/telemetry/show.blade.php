@extends('accelerator::telemetry.layout')

@section('title', class_basename($group['exception_class']))

@section('content')
<div class="stack">
    <div class="row">
        <a class="button" href="{{ route('accelerator.telemetry.index') }}">← Back</a>
        <span class="spacer"></span>
        <button id="copy-markdown" type="button">Copy Markdown</button>
    </div>

    <header>
        <p class="eyebrow">Group #{{ $group['id'] }} · {{ $group['fingerprint'] }}</p>
        <h1>{{ class_basename($group['exception_class']) }}</h1>
        <p class="message">{{ $group['message'] }}</p>
    </header>

    <section class="grid-2">
        <div class="card">
            <h2>Group</h2>
            <dl class="definition">
                <dt>Status</dt><dd><span class="badge badge-{{ $group['status'] }}">{{ $group['status'] }}</span></dd>
                <dt>Occurrences</dt><dd>{{ number_format((int) $group['occurrence_count']) }}</dd>
                <dt>Location</dt><dd class="mono">{{ $group['source_file'] }}:{{ $group['source_line'] }}</dd>
                <dt>Route</dt><dd class="mono">{{ $group['route_name'] ?? 'n/a' }}</dd>
                <dt>First seen</dt><dd class="mono">{{ $group['first_seen_at'] }}</dd>
                <dt>Last seen</dt><dd class="mono">{{ $group['last_seen_at'] }}</dd>
                <dt>Last notification</dt><dd class="mono">{{ $group['last_notified_at'] ?? 'never' }}</dd>
            </dl>
        </div>
        <div class="card">
            <h2>Manage</h2>
            <form class="actions" method="POST" action="{{ route('accelerator.telemetry.status', $group['id']) }}">
                @csrf
                <select name="status">
                    @foreach(['open', 'resolved', 'muted'] as $status)
                        <option value="{{ $status }}" @selected($group['status'] === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button type="submit">Update status</button>
            </form>
        </div>
    </section>

    @if($source !== [])
        <section class="card">
            <h2>Application source</h2>
            <pre class="source">@foreach($source as $line)<span class="source-line {{ $line['highlighted'] ? 'focus' : '' }}">{{ str_pad((string) $line['line'], 5, ' ', STR_PAD_LEFT) }}  {{ $line['code'] }}</span>@endforeach</pre>
        </section>
    @endif

    @if($latest)
        <section class="grid-2">
            <div class="card">
                <h2>Latest occurrence</h2>
                <dl class="definition">
                    <dt>Actor</dt><dd>{{ $latest['user_label'] ?? 'User '.$latest['user_id'] }} <span class="muted">#{{ $latest['user_id'] }}</span></dd>
                    <dt>Method</dt><dd class="mono">{{ $latest['method'] ?? 'n/a' }}</dd>
                    <dt>Route</dt><dd class="mono">{{ $group['route_name'] ?? 'n/a' }}</dd>
                    <dt>URL</dt><dd class="mono">{{ $latest['url'] }}</dd>
                    <dt>IP</dt><dd class="mono">{{ $latest['ip'] ?? 'n/a' }}</dd>
                    <dt>Duration</dt><dd>{{ $latest['duration_ms'] !== null ? number_format((float) $latest['duration_ms'], 2).' ms' : 'n/a' }}</dd>
                    <dt>Occurred</dt><dd class="mono">{{ $latest['occurred_at'] }}</dd>
                    <dt>Buffer ID</dt><dd class="mono">{{ $latest['buffer_id'] }}</dd>
                </dl>
            </div>
            <div class="card">
                <h2>Optional request context</h2>
                @if($requestContext === [])
                    <p class="muted">Headers, query, and payload capture are disabled by default.</p>
                @else
                    <pre>{{ json_encode($requestContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </div>
        </section>

        <section class="card">
            <h2>Latest stack trace</h2>
            <pre>{{ $latest['stack_trace'] }}</pre>
        </section>
    @endif

    <section class="stack">
        <h2>Recent occurrences</h2>
        @foreach($occurrences as $occurrence)
            <details @if($loop->first) open @endif>
                <summary><strong>{{ $occurrence['occurred_at'] }}</strong> · {{ $occurrence['user_label'] ?? 'User '.$occurrence['user_id'] }} · <span class="mono">{{ $occurrence['method'] }} {{ $group['route_name'] ?? '' }}</span></summary>
                <div class="stack">
                    <p>{{ $occurrence['message'] }}</p>
                    <pre>{{ $occurrence['stack_trace'] }}</pre>
                </div>
            </details>
        @endforeach
    </section>

    @if($occurrences->hasPages())
        <nav class="pagination">
            @if($occurrences->onFirstPage())<span class="button muted">Previous</span>@else<a class="button" href="{{ $occurrences->previousPageUrl() }}">Previous</a>@endif
            <span class="muted">Page {{ $occurrences->currentPage() }} / {{ $occurrences->lastPage() }}</span>
            @if($occurrences->hasMorePages())<a class="button" href="{{ $occurrences->nextPageUrl() }}">Next</a>@else<span class="button muted">Next</span>@endif
        </nav>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('copy-markdown')?.addEventListener('click', async (event) => {
        await navigator.clipboard.writeText(@js($markdown));
        event.currentTarget.textContent = 'Copied';
        window.setTimeout(() => event.currentTarget.textContent = 'Copy Markdown', 1200);
    });
</script>
@endpush
