@extends('accelerator::telemetry.layout')

@section('title', 'Exception groups')

@section('content')
<div class="stack">
    <header>
        <p class="eyebrow">Authenticated exception monitoring</p>
        <h1>Exception groups</h1>
        <p class="muted">{{ number_format($groups->total()) }} groups · {{ $statistics['database_id'] }} schema {{ $statistics['schema_version'] }}</p>
    </header>

    <section class="stats" aria-label="Telemetry health">
        @foreach([
            'Captured' => $runtime['captured'] ?? 0,
            'Persisted' => $runtime['persisted'] ?? 0,
            'Buffer depth' => $runtime['buffered'] ?? 0,
            'Dropped' => $runtime['dropped'] ?? 0,
            'Rejected payloads' => $statistics['malformed'] ?? 0,
            'Flush failures' => $runtime['flush_failures'] ?? 0,
            'Notification failures' => $runtime['notification_failures'] ?? 0,
            'Pending notifications' => $statistics['pending_notifications'] ?? 0,
        ] as $label => $value)
            <div class="stat"><span class="muted">{{ $label }}</span><strong>{{ number_format((int) $value) }}</strong></div>
        @endforeach
        <div class="stat"><span class="muted">SQLite size</span><strong>{{ number_format(((int) $statistics['database_bytes']) / 1024, 1) }} KB</strong></div>
    </section>

    @if(! $runtime['available'])
        <section class="card alert"><strong>Runtime tables unavailable</strong><p>Open this dashboard through Octane Swoole after restarting it with the v2 table config.</p></section>
    @elseif(filled($runtime['last_error'] ?? null))
        <section class="card alert"><strong>Latest runtime error</strong><p>{{ $runtime['last_error'] }}</p></section>
    @endif

    <nav class="filters" aria-label="Status filter">
        <a class="button {{ $activeStatus === null ? 'active' : '' }}" href="{{ route('accelerator.telemetry.index') }}">All</a>
        @foreach(['open', 'resolved', 'muted'] as $status)
            <a class="button {{ $activeStatus === $status ? 'active' : '' }}" href="{{ route('accelerator.telemetry.index', ['status' => $status]) }}">{{ ucfirst($status) }}</a>
        @endforeach
    </nav>

    @if($groups->isEmpty())
        <section class="card muted">No exception groups match this filter.</section>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Status</th><th>Issue</th><th>Location</th><th>Actor</th><th>Count</th><th>Last seen</th><th>Change</th></tr></thead>
                <tbody>
                @foreach($groups as $group)
                    <tr>
                        <td><span class="badge badge-{{ $group['status'] }}">{{ $group['status'] }}</span></td>
                        <td class="issue">
                            <a href="{{ route('accelerator.telemetry.show', $group['id']) }}">{{ class_basename($group['exception_class']) }}</a>
                            <p class="message">{{ $group['message'] }}</p>
                            @if($group['route_name'])<p class="muted mono">{{ $group['route_name'] }}</p>@endif
                        </td>
                        <td class="mono">{{ $group['source_file'] }}:{{ $group['source_line'] }}</td>
                        <td>{{ $group['latest_user_label'] ?: 'User '.$group['latest_user_id'] }}<br><span class="muted">{{ number_format((int) $group['impacted_users']) }} users</span></td>
                        <td>{{ number_format((int) $group['occurrence_count']) }}</td>
                        <td class="mono">{{ $group['last_seen_at'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('accelerator.telemetry.status', $group['id']) }}">
                                @csrf
                                <select name="status" aria-label="Change status" onchange="this.form.submit()">
                                    @foreach(['open', 'resolved', 'muted'] as $status)
                                        <option value="{{ $status }}" @selected($group['status'] === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($groups->hasPages())
        <nav class="pagination">
            @if($groups->onFirstPage())<span class="button muted">Previous</span>@else<a class="button" href="{{ $groups->previousPageUrl() }}">Previous</a>@endif
            <span class="muted">Page {{ $groups->currentPage() }} / {{ $groups->lastPage() }}</span>
            @if($groups->hasMorePages())<a class="button" href="{{ $groups->nextPageUrl() }}">Next</a>@else<span class="button muted">Next</span>@endif
        </nav>
    @endif
</div>
@endsection
