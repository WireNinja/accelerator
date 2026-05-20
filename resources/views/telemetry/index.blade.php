@extends('accelerator::telemetry.layout')

@section('title', 'Exception Groups')

@php
    $activeStatus = request('status');
@endphp

@section('content')
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-950">Exception Groups</h2>
            <p class="text-sm text-zinc-500">
                {{ number_format($groups->total()) }} groups
                @if($activeStatus)
                    filtered by <x-accelerator::telemetry.status-badge :status="$activeStatus" />
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('accelerator.telemetry.index') }}" class="rounded-md border px-3 py-1.5 text-sm font-medium {{ $activeStatus === null ? 'border-zinc-950 bg-zinc-950 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100' }}">
                All
            </a>
            @foreach(['open', 'resolved', 'muted'] as $status)
                <a href="{{ route('accelerator.telemetry.index', ['status' => $status]) }}" class="rounded-md border px-3 py-1.5 text-sm font-medium {{ $activeStatus === $status ? 'border-zinc-950 bg-zinc-950 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-100' }}">
                    {{ ucfirst($status) }}
                </a>
            @endforeach
        </div>
    </div>

    @if($groups->isEmpty())
        <div class="rounded-lg border border-zinc-200 bg-white p-6 text-sm text-zinc-500">
            No exception groups found.
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-500">
                        <tr>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Exception</th>
                            <th class="px-4 py-3">Focused File</th>
                            <th class="px-4 py-3">Latest User</th>
                            <th class="px-4 py-3">Waterfall</th>
                            <th class="px-4 py-3 text-right">Count</th>
                            <th class="px-4 py-3">Last Seen</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($groups as $group)
                            <tr class="align-top hover:bg-zinc-50">
                                <td class="px-4 py-3">
                                    <x-accelerator::telemetry.status-badge :status="$group['status']" />
                                </td>
                                <td class="max-w-md px-4 py-3">
                                    <a href="{{ route('accelerator.telemetry.show', $group['id']) }}" class="font-semibold text-zinc-950 hover:underline">
                                        {{ class_basename($group['class']) }}
                                    </a>
                                    <p class="mt-1 truncate font-mono text-xs text-zinc-500">{{ $group['class'] }}</p>
                                    @if(($group['message'] ?? null) || ($group['latest_message'] ?? null))
                                        <p class="mt-1 text-sm text-zinc-600">{{ $group['message'] ?? $group['latest_message'] }}</p>
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-3">
                                    <p class="truncate font-mono text-xs text-zinc-600">{{ str_replace(base_path().'/', '', $group['file']) }}:{{ $group['line'] }}</p>
                                </td>
                                <td class="max-w-[14rem] px-4 py-3">
                                    <x-accelerator::telemetry.user-chip
                                        :name="$group['latest_user_name'] ?? null"
                                        :username="$group['latest_user_username'] ?? null"
                                        :email="$group['latest_user_email'] ?? null"
                                    />
                                    <p class="mt-1 text-xs text-zinc-500">{{ number_format((int) ($group['user_count'] ?? 0)) }} impacted users</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-600">
                                    @if($group['latest_duration_ms'] !== null)
                                        <div>{{ number_format((float) $group['latest_duration_ms'], 2) }}ms req</div>
                                    @else
                                        <div class="text-zinc-400">n/a</div>
                                    @endif
                                    @if($group['latest_db_query_count'] !== null)
                                        <div>{{ number_format((int) $group['latest_db_query_count']) }}q / {{ number_format((float) $group['latest_db_duration_ms'], 2) }}ms DB</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-zinc-900">
                                    {{ number_format($group['occurrence_count']) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-500">
                                    {{ $group['last_seen_at'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-accelerator::telemetry.group-actions :group="$group" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($groups->hasPages())
            <div class="flex items-center justify-center gap-2 text-sm">
                @if($groups->onFirstPage())
                    <span class="rounded-md border border-zinc-200 bg-zinc-100 px-3 py-1.5 text-zinc-400">Prev</span>
                @else
                    <a href="{{ $groups->previousPageUrl() }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Prev</a>
                @endif

                <span class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 text-zinc-500">
                    Page {{ $groups->currentPage() }} of {{ $groups->lastPage() }}
                </span>

                @if($groups->hasMorePages())
                    <a href="{{ $groups->nextPageUrl() }}" class="rounded-md border border-zinc-200 bg-white px-3 py-1.5 font-medium text-zinc-700 hover:bg-zinc-100">Next</a>
                @else
                    <span class="rounded-md border border-zinc-200 bg-zinc-100 px-3 py-1.5 text-zinc-400">Next</span>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
