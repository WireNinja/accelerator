@extends('accelerator::telemetry.layout')

@section('title', class_basename($group['class']))

@section('content')
<div class="flex flex-between mb-16">
    <div>
        <a href="{{ route('accelerator.telemetry.index') }}">← Back to groups</a>
    </div>
    <div class="flex gap-8">
        @if($group['status'] === 'open')
            <form method="POST" action="{{ route('accelerator.telemetry.resolve', $group['id']) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Mark Resolved</button>
            </form>
        @else
            <form method="POST" action="{{ route('accelerator.telemetry.reopen', $group['id']) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-danger">Reopen</button>
            </form>
        @endif
    </div>
</div>

<div class="card mb-16">
    <h2>
        <span class="badge badge-{{ $group['status'] }}">{{ $group['status'] }}</span>
        {{ $group['class'] }}
    </h2>
    <table>
        <tr><th style="width:150px">File</th><td class="mono">{{ str_replace(base_path().'/', '', $group['file']) }}:{{ $group['line'] }}</td></tr>
        <tr><th>Total Occurrences</th><td>{{ number_format($group['occurrence_count']) }}</td></tr>
        <tr><th>First Seen</th><td>{{ $group['first_seen_at'] }}</td></tr>
        <tr><th>Last Seen</th><td>{{ $group['last_seen_at'] }}</td></tr>
        <tr><th>Fingerprint</th><td class="mono text-muted">{{ $group['fingerprint'] }}</td></tr>
    </table>
</div>

<h2 class="mb-8">Recent Occurrences</h2>

@foreach($occurrences as $occ)
<div class="card">
    <div class="flex flex-between mb-8">
        <div>
            <strong class="text-sm">{{ $occ['created_at'] }}</strong>
            @if($occ['user_id'])
                — User #{{ $occ['user_id'] }}
            @endif
        </div>
        <div class="text-sm text-muted">
            {{ $occ['method'] }} {{ $occ['url'] }}
            @if($occ['duration_ms'])
                — {{ $occ['duration_ms'] }}ms
            @endif
            @if($occ['memory_usage_bytes'])
                — {{ number_format($occ['memory_usage_bytes'] / 1024 / 1024, 1) }}MB
            @endif
        </div>
    </div>

    <p class="mb-8"><strong>{{ $occ['message'] }}</strong></p>

    <pre>{{ $occ['stack_trace'] }}</pre>

    @if($occ['request_headers'])
        <details class="mt-16">
            <summary class="text-sm" style="cursor:pointer">Request Headers</summary>
            <pre>{{ json_encode(json_decode($occ['request_headers'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @endif

    @if($occ['request_payload'])
        <details class="mt-16">
            <summary class="text-sm" style="cursor:pointer">Request Payload</summary>
            <pre>{{ json_encode(json_decode($occ['request_payload'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @endif

    <div class="text-sm text-muted mt-16">
        IP: {{ $occ['ip'] ?? 'unknown' }}
    </div>
</div>
@endforeach

@if($occurrences->hasPages())
    <div class="pagination">
        @if($occurrences->onFirstPage())
            <span class="text-muted">← Prev</span>
        @else
            <a href="{{ $occurrences->previousPageUrl() }}">← Prev</a>
        @endif
        <span>Page {{ $occurrences->currentPage() }} of {{ $occurrences->lastPage() }}</span>
        @if($occurrences->hasMorePages())
            <a href="{{ $occurrences->nextPageUrl() }}">Next →</a>
        @else
            <span class="text-muted">Next →</span>
        @endif
    </div>
@endif
@endsection
