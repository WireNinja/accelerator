@props(['group'])

<div {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    @if($group['status'] === 'open')
        <x-accelerator::telemetry.action-form
            :action="route('accelerator.telemetry.resolve', $group['id'])"
            label="Resolve"
            variant="success"
        />
        <x-accelerator::telemetry.action-form
            :action="route('accelerator.telemetry.mute', $group['id'])"
            label="Mute"
        />
    @elseif($group['status'] === 'resolved')
        <x-accelerator::telemetry.action-form
            :action="route('accelerator.telemetry.reopen', $group['id'])"
            label="Reopen"
            variant="danger"
        />
        <x-accelerator::telemetry.action-form
            :action="route('accelerator.telemetry.mute', $group['id'])"
            label="Mute"
        />
    @elseif($group['status'] === 'muted')
        <x-accelerator::telemetry.action-form
            :action="route('accelerator.telemetry.reopen', $group['id'])"
            label="Reopen"
            variant="danger"
        />
    @endif
</div>
