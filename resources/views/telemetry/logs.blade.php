@extends('accelerator::telemetry.layout')

@section('title', 'Log Reader')

@section('content')
<div class="flex flex-col gap-6">
    <section class="flex flex-col gap-2">
        <p class="text-sm text-neutral-500">Logs</p>
        <h1 class="text-3xl font-semibold tracking-tight text-white">{{ $logFile }}</h1>
        <p class="text-sm text-neutral-400">Newest entries first, grouped by Laravel log occurrence.</p>
    </section>

    <div class="flex flex-wrap gap-2">
        @if($page > 1)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-sm text-neutral-300 hover:bg-white/[8%]">Newer</a>
        @endif
        @if($hasMore)
            <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-sm text-neutral-300 hover:bg-white/[8%]">Older</a>
        @endif
    </div>

    @if(empty($entries))
        <div class="rounded-xl border border-white/10 bg-[#1d1d1d] p-8 text-center text-sm text-neutral-500">
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
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page - 1]) }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Newer</a>
            @endif
            @if($hasMore)
                <a href="{{ route('accelerator.telemetry.logs', ['page' => $page + 1]) }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Older</a>
            @endif
        </div>
        <span class="text-neutral-500">Page {{ $page }}</span>
    </div>
</div>
@endsection
