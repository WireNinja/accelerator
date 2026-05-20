@extends('accelerator::telemetry.layout')

@section('title', class_basename($group['class']))

@php
    $focusedOccurrence = $occurrences->getCollection()->first();
@endphp

@section('content')
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('accelerator.telemetry.index') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950">Back to groups</a>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-accelerator::telemetry.status-badge :status="$group['status']" />
                <h2 class="truncate text-lg font-semibold text-zinc-950">{{ class_basename($group['class']) }}</h2>
            </div>
            <p class="mt-1 truncate font-mono text-xs text-zinc-500">{{ $group['class'] }}</p>
            @if($group['message'] ?? null)
                <p class="mt-2 max-w-3xl text-sm text-zinc-700">{{ $group['message'] }}</p>
            @endif
        </div>

        <x-accelerator::telemetry.group-actions :group="$group" />
    </div>

    <section class="rounded-lg border border-zinc-200 bg-white p-4">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <dt class="text-xs font-semibold uppercase text-zinc-500">Location</dt>
                <dd class="mt-1 break-words font-mono text-xs text-zinc-900">{{ str_replace(base_path().'/', '', $group['file']) }}:{{ $group['line'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase text-zinc-500">Occurrences</dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-zinc-900">{{ number_format($group['occurrence_count']) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase text-zinc-500">First Seen</dt>
                <dd class="mt-1 font-mono text-xs text-zinc-700">{{ $group['first_seen_at'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase text-zinc-500">Last Seen</dt>
                <dd class="mt-1 font-mono text-xs text-zinc-700">{{ $group['last_seen_at'] }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-5">
                <dt class="text-xs font-semibold uppercase text-zinc-500">Fingerprint</dt>
                <dd class="mt-1 break-all font-mono text-xs text-zinc-500">{{ $group['fingerprint'] }}</dd>
            </div>
        </dl>
    </section>

    @if($focusedOccurrence)
        <div class="grid gap-4 xl:grid-cols-[1fr_24rem]">
            <x-accelerator::telemetry.source-snippet
                :file="$focusedOccurrence['source_file'] ?? $group['file']"
                :line="$focusedOccurrence['source_line'] ?? $group['line']"
                :class="$focusedOccurrence['source_class'] ?? null"
                :function="$focusedOccurrence['source_function'] ?? null"
                :snippet="$focusedOccurrence['source_snippet'] ?? []"
            />

            <div class="flex flex-col gap-4">
                <section class="rounded-lg border border-zinc-200 bg-white p-4">
                    <p class="mb-3 text-xs font-semibold uppercase text-zinc-500">Latest Actor</p>
                    <x-accelerator::telemetry.user-chip
                        :user-id="$focusedOccurrence['user_id']"
                        :name="$focusedOccurrence['user_name'] ?? null"
                        :username="$focusedOccurrence['user_username'] ?? null"
                        :email="$focusedOccurrence['user_email'] ?? null"
                    />
                </section>

                <x-accelerator::telemetry.waterfall
                    :events="$focusedOccurrence['timeline_events'] ?? []"
                    :total-ms="$focusedOccurrence['duration_ms']"
                    :db-query-count="$focusedOccurrence['db_query_count'] ?? null"
                    :db-duration-ms="$focusedOccurrence['db_duration_ms'] ?? null"
                />
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-zinc-950">Recent Occurrences</h3>
        <span class="text-sm text-zinc-500">{{ number_format($occurrences->total()) }} total</span>
    </div>

    <div class="flex flex-col gap-3">
        @foreach($occurrences as $occurrence)
            <x-accelerator::telemetry.occurrence-card :occurrence="$occurrence" />
        @endforeach
    </div>

    @if($occurrences->hasPages())
        <div class="flex items-center justify-center gap-2 text-sm">
            @if($occurrences->onFirstPage())
                <span class="rounded-md border border-zinc-200 bg-zinc-100 px-3 py-1.5 text-zinc-400">Prev</span>
            @else
                <a href="{{ $occurrences->previousPageUrl() }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Prev</a>
            @endif

            <span class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-zinc-500">
                Page {{ $occurrences->currentPage() }} of {{ $occurrences->lastPage() }}
            </span>

            @if($occurrences->hasMorePages())
                <a href="{{ $occurrences->nextPageUrl() }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Next</a>
            @else
                <span class="rounded-md border border-zinc-200 bg-zinc-100 px-3 py-1.5 text-zinc-400">Next</span>
            @endif
        </div>
    @endif
</div>
@endsection
