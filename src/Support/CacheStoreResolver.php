<?php

namespace WireNinja\Accelerator\Support;

class CacheStoreResolver
{
    public static function withOctaneFirst(): string
    {
        return is_octane_runtime() && config('accelerator.cache.allow_swoole')
            ? 'octane'
            : (self::withRedisFirst());
    }

    /**
     * Resolve the best available cache store for this process.
     *
     * Priority: Octane in-memory (any Octane server) → configured cache default.
     */
    public static function withRedisFirst(): string
    {
        return config('accelerator.cache.allow_redis')
            ? 'redis'
            : (config('accelerator.cache.allow_database')
                ? 'database'
                : 'file');
    }
}
