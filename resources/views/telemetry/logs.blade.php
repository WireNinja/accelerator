@extends('accelerator::telemetry.layout')

@section('title', 'Log Reader')

@section('content')
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-950">Logs</h2>
            <p class="text-sm text-zinc-500">
                {{ $logFile }} - newest entries first - grouped by Laravel log occurrence
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if($page > 1)
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100">Newer</a>
            @endif
            @if($hasMore)
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100">Older</a>
            @endif
        </div>
    </div>

    @if(empty($entries))
        <div class="rounded-lg border border-zinc-200 bg-white p-6 text-sm text-zinc-500">
            Log file is empty or not found.
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($entries as $entry)
                <x-accelerator::telemetry.log-entry :entry="$entry" />
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between text-sm">
        <div class="flex gap-2">
            @if($page > 1)
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Newer</a>
            @endif
            @if($hasMore)
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Older</a>
            @endif
        </div>
        <span class="text-zinc-500">Page {{ $page }}</span>
    </div>
</div>
@endsection
