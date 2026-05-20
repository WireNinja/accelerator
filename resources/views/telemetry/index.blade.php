@extends('accelerator::telemetry.layout')

@section('title', 'Exception Groups')

@section('content')
<div class="flex flex-between mb-16">
    <div>
        <strong>{{ $groups->total() }}</strong> exception group(s)
        @if(request('status'))
            — filtered: <span class="badge badge-{{ request('status') }}">{{ request('status') }}</span>
            <a href="{{ route('accelerator.telemetry.index') }}" class="text-sm">(clear)</a>
        @endif
    </div>
    <div class="flex gap-8">
        <a href="{{ route('accelerator.telemetry.index', ['status' => 'open']) }}" class="btn btn-sm {{ request('status') === 'open' ? 'btn-danger' : '' }}">Open</a>
        <a href="{{ route('accelerator.telemetry.index', ['status' => 'resolved']) }}" class="btn btn-sm {{ request('status') === 'resolved' ? 'btn-success' : '' }}">Resolved</a>
        <a href="{{ route('accelerator.telemetry.index', ['status' => 'muted']) }}" class="btn btn-sm">Muted</a>
    </div>
</div>

@if($groups->isEmpty())
    <div class="card">
        <p class="text-muted">No exception groups found.</p>
    </div>
@else
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Exception</th>
                <th>Location</th>
                <th>Count</th>
                <th>First Seen</th>
                <th>Last Seen</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
            <tr>
                <td><span class="badge badge-{{ $group['status'] }}">{{ $group['status'] }}</span></td>
                <td>
                    <a href="{{ route('accelerator.telemetry.show', $group['id']) }}">
                        {{ class_basename($group['class']) }}
                    </a>
                    <br><span class="text-muted text-sm">{{ $group['class'] }}</span>
                </td>
                <td class="mono text-sm">{{ str_replace(base_path().'/', '', $group['file']) }}:{{ $group['line'] }}</td>
                <td><span class="count-badge">{{ number_format($group['occurrence_count']) }}</span></td>
                <td class="text-sm text-muted">{{ $group['first_seen_at'] }}</td>
                <td class="text-sm text-muted">{{ $group['last_seen_at'] }}</td>
                <td>
                    @if($group['status'] === 'open')
                        <form method="POST" action="{{ route('accelerator.telemetry.resolve', $group['id']) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">Resolve</button>
                        </form>
                    @elseif($group['status'] === 'resolved')
                        <form method="POST" action="{{ route('accelerator.telemetry.reopen', $group['id']) }}" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger">Reopen</button>
                        </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($groups->hasPages())
        <div class="pagination">
            @if($groups->onFirstPage())
                <span class="text-muted">← Prev</span>
            @else
                <a href="{{ $groups->previousPageUrl() }}">← Prev</a>
            @endif

            <span>Page {{ $groups->currentPage() }} of {{ $groups->lastPage() }}</span>

            @if($groups->hasMorePages())
                <a href="{{ $groups->nextPageUrl() }}">Next →</a>
            @else
                <span class="text-muted">Next →</span>
            @endif
        </div>
    @endif
@endif
@endsection
