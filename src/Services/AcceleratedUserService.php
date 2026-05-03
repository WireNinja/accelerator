<?php

namespace WireNinja\Accelerator\Services;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schedule;
use WireNinja\Accelerator\Model\AcceleratedUser;
use WireNinja\Accelerator\Support\CacheStoreResolver;

class AcceleratedUserService
{
    /**
     * Opinionated way to schedule the pending last_seen flush.
     */
    public static function schedule(): Event
    {
        return Schedule::command('accelerator:flush-last-seen')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Redis hash key that accumulates pending last_seen_at flushes.
     * Read by the scheduler — must live in Redis, not Octane in-memory.
     */
    protected const PENDING_HASH = 'accelerator:last_seen_pending';

    /**
     * Record the user's presence without touching the database.
     *
     * Per-request debounce via the fastest available cache store (Octane/Redis).
     * The timestamp is queued into a Redis hash for batch DB flush by the scheduler.
     */
    public function touchOnlineStatus(AcceleratedUser $user): void
    {
        $key = $this->cacheKey($user);
        $store = Cache::store(CacheStoreResolver::withOctaneFirst());

        if ($store->has($key)) {
            return;
        }

        $store->put($key, true, now()->addMinute());

        // Queue for bulk DB write — Redis hash survives across processes
        Redis::connection()->hset(self::PENDING_HASH, (string) $user->getAuthIdentifier(), now()->timestamp);
    }

    /**
     * Flush the pending Redis hash to the database in a single bulk upsert,
     * then clear the flushed entries. Called by the scheduler every minute.
     */
    public function flushPendingLastSeen(): void
    {
        $pending = Redis::connection()->hgetall(self::PENDING_HASH);

        if (empty($pending)) {
            return;
        }

        $rows = collect($pending)
            ->map(fn (string $ts, string $id) => [
                'id' => (int) $id,
                'last_seen_at' => now()->createFromTimestamp((int) $ts),
            ])
            ->values()
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('users')->upsert($chunk, ['id'], ['last_seen_at']);
        }

        Redis::connection()->del(self::PENDING_HASH);
    }

    public function isOnline(AcceleratedUser $user, int $thresholdMinutes = 5): bool
    {
        return $user->last_seen_at?->gte(now()->subMinutes($thresholdMinutes)) ?? false;
    }

    protected function cacheKey(AcceleratedUser $user): string
    {
        return 'accelerator:last_seen:'.$user->getAuthIdentifier();
    }

    /**
     * @template TUser of AcceleratedUser
     *
     * @param  TUser  $user
     * @return TUser
     */
    public function suspend(AcceleratedUser $user, ?AuthenticatableContract $suspender = null, ?string $reason = null): AcceleratedUser
    {
        $user->forceFill([
            'suspended_at' => now(),
            'suspended_by' => $suspender?->getAuthIdentifier(),
            'suspension_reason' => filled($reason) ? $reason : $user->suspension_reason,
        ])->save();

        return $user->refresh();
    }

    /**
     * @template TUser of AcceleratedUser
     *
     * @param  TUser  $user
     * @return TUser
     */
    public function unsuspend(AcceleratedUser $user): AcceleratedUser
    {
        $user->forceFill([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ])->save();

        return $user->refresh();
    }
}
