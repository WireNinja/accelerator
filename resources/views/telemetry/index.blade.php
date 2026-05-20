@extends('accelerator::telemetry.layout')

@section('title', 'Exception Groups')

@php
    $activeStatus = request('status');
@endphp

@section('content')
<div class="flex flex-col gap-8">
    <section class="flex flex-col gap-5">
        <div>
            <p class="mb-3 text-sm text-neutral-500">Issues</p>
            <h1 class="text-3xl font-semibold tracking-tight text-white">Exception groups</h1>
            <p class="mt-2 text-sm text-neutral-400">{{ number_format($groups->total()) }} captured groups</p>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('accelerator.telemetry.index') }}" class="rounded-md border px-3 py-1.5 text-sm {{ $activeStatus === null ? 'border-white/15 bg-white/10 text-white' : 'border-white/10 bg-white/[3%] text-neutral-400 hover:bg-white/[8%] hover:text-white' }}">
                    All
                </a>
                @foreach(['open', 'resolved', 'muted'] as $status)
                    <a href="{{ route('accelerator.telemetry.index', ['status' => $status]) }}" class="rounded-md border px-3 py-1.5 text-sm {{ $activeStatus === $status ? 'border-white/15 bg-white/10 text-white' : 'border-white/10 bg-white/[3%] text-neutral-400 hover:bg-white/[8%] hover:text-white' }}">
                        {{ ucfirst($status) }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if($groups->isEmpty())
        <div class="rounded-xl border border-white/10 bg-[#1d1d1d] p-8 text-center text-sm text-neutral-500">
            No exception groups found.
        </div>
    @else
        <section class="overflow-hidden rounded-xl border border-white/10 bg-[#1d1d1d]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-white/10 text-sm">
                    <thead class="bg-white/[3%] text-left font-mono text-xs uppercase text-neutral-500">
                        <tr>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Issue</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Perf</th>
                            <th class="px-4 py-3 text-right">Count</th>
                            <th class="px-4 py-3">Last seen</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($groups as $group)
                            <tr class="align-top hover:bg-white/[3%]">
                                <td class="px-4 py-3">
                                    <x-accelerator::telemetry.status-badge :status="$group['status']" />
                                </td>
                                <td class="max-w-md px-4 py-3">
                                    <a href="{{ route('accelerator.telemetry.show', $group['id']) }}" class="font-semibold text-neutral-100 hover:text-white hover:underline">
                                        {{ class_basename($group['class']) }}
                                    </a>
                                    <p class="mt-1 truncate font-mono text-xs text-neutral-500">{{ $group['class'] }}</p>
                                    @if(($group['message'] ?? null) || ($group['latest_message'] ?? null))
                                        <p class="mt-2 text-sm text-neutral-300">{{ $group['message'] ?? $group['latest_message'] }}</p>
                                    @endif
                                </td>
                                <td class="max-w-sm px-4 py-3">
                                    <p class="truncate font-mono text-xs text-neutral-400">{{ str_replace(base_path().'/', '', $group['file']) }}:{{ $group['line'] }}</p>
                                </td>
                                <td class="max-w-[14rem] px-4 py-3">
                                    <x-accelerator::telemetry.user-chip
                                        :name="$group['latest_user_name'] ?? null"
                                        :username="$group['latest_user_username'] ?? null"
                                        :email="$group['latest_user_email'] ?? null"
                                    />
                                    <p class="mt-1 text-xs text-neutral-500">{{ number_format((int) ($group['user_count'] ?? 0)) }} impacted users</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-neutral-400">
                                    @if($group['latest_duration_ms'] !== null)
                                        <div>{{ number_format((float) $group['latest_duration_ms'], 2) }}ms request</div>
                                    @else
                                        <div class="text-neutral-600">n/a</div>
                                    @endif
                                    @if($group['latest_db_query_count'] !== null)
                                        <div>{{ number_format((int) $group['latest_db_query_count']) }}q / {{ number_format((float) $group['latest_db_duration_ms'], 2) }}ms DB</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-neutral-100">
                                    {{ number_format($group['occurrence_count']) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-neutral-500">
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
        </section>

        @if($groups->hasPages())
            <div class="flex items-center justify-center gap-2 text-sm">
                @if($groups->onFirstPage())
                    <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-600">Prev</span>
                @else
                    <a href="{{ $groups->previousPageUrl() }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Prev</a>
                @endif

                <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-500">
                    Page {{ $groups->currentPage() }} of {{ $groups->lastPage() }}
                </span>

                @if($groups->hasMorePages())
                    <a href="{{ $groups->nextPageUrl() }}" class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-300 hover:bg-white/[8%]">Next</a>
                @else
                    <span class="rounded-md border border-white/10 bg-white/[3%] px-3 py-1.5 text-neutral-600">Next</span>
                @endif
            </div>
        @endif
    @endif
</div>
@endsection
