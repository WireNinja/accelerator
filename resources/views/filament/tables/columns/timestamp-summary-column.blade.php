@php
    use Carbon\CarbonInterface;
    use Illuminate\Support\Carbon;

    $state = $getState();
    $timestamp = null;

    if ($state instanceof CarbonInterface) {
        $timestamp = $state;
    } elseif (filled($state)) {
        $timestamp = Carbon::parse($state);
    }
@endphp

<div {{ $getExtraAttributeBag()->class(['min-w-44 py-1']) }}>
    @if ($timestamp)
        <div class="min-w-0 space-y-0.5">
            <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                {{ $timestamp->diffForHumans() }}
            </div>

            <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                {{ $timestamp->translatedFormat('d M Y') }} pukul {{ $timestamp->format('H:i') }}
            </div>
        </div>
    @else
        <span class="text-sm text-gray-400 dark:text-gray-500">
            {{ $getEmptyLabel() }}
        </span>
    @endif
</div>
