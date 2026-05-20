@extends('accelerator::telemetry.layout')

@section('title', 'Log Reader')

@section('content')
<div class="flex flex-between mb-16">
    <div>
        <strong>{{ $logFile }}</strong>
        <span class="text-muted text-sm">({{ $totalLines }} lines, showing {{ $startLine }}-{{ $endLine }})</span>
    </div>
    <div class="flex gap-8">
        @if($page > 1)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="btn btn-sm">← Newer</a>
        @endif
        @if($hasMore)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="btn btn-sm">Older →</a>
        @endif
    </div>
</div>

@if(empty($lines))
    <div class="card">
        <p class="text-muted">Log file is empty or not found.</p>
    </div>
@else
    <pre>@foreach($lines as $line){{ $line }}
@endforeach</pre>
@endif

<div class="flex flex-between mt-16">
    <div class="flex gap-8">
        @if($page > 1)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="btn btn-sm">← Newer</a>
        @endif
        @if($hasMore)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="btn btn-sm">Older →</a>
        @endif
    </div>
    <span class="text-sm text-muted">Page {{ $page }}</span>
</div>
@endsection
