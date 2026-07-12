<?php

namespace Novalites\Cache;

use Novalites\Cache\CacheDriverInterface;
use Novalites\Cache\DatabaseCacheDriver;
use Novalites\Cache\RedisCacheDriver;
use Closure;

class Cache
{
    protected static ?CacheDriverInterface $driver = null;

    private function __construct()
    {
        // Prevent direct instantiation
    }

    public static function driver(): CacheDriverInterface
    {
        if (self::$driver === null) {
            self::$driver = self::resolveDriver();
        }
        return self::$driver;
    }

    protected static function resolveDriver(): CacheDriverInterface
    {
        $config = require constant('BASE_PATH') . '/config/cache.php';
        $default = $config['default'];

        return match ($default) {
            'redis'    => new RedisCacheDriver($config['drivers']['redis']),
            'database' => new DatabaseCacheDriver(),
            default    => throw new \InvalidArgumentException("Cache driver '{$default}' ga dikenali."),
        };
    }

    /**
     * Ambil value dari cache. Kalau ga ada, return default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::driver()->get($key, $default);
    }

    /**
     * Simpan value ke cache dengan TTL (detik).
     */
    public static function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::driver()->put($key, $value, $ttl);
    }

    /**
     * Simpan value tanpa expired.
     */
    public static function forever(string $key, mixed $value): bool
    {
        return self::driver()->forever($key, $value);
    }

    /**
     * Ambil dari cache, kalau ga ada eksekusi callback dan simpan hasilnya.
     * Ini yang paling sering kepake — pattern "remember".
     */
    public static function remember(string $key, int $ttl, Closure $callback): mixed
    {
        $value = self::get($key, '__CACHE_MISS__');

        if ($value !== '__CACHE_MISS__') {
            return $value;
        }

        $value = $callback();
        self::put($key, $value, $ttl);

        return $value;
    }

    /**
     * Sama kayak remember(), tapi ga pernah expired.
     */
    public static function rememberForever(string $key, Closure $callback): mixed
    {
        $value = self::get($key, '__CACHE_MISS__');

        if ($value !== '__CACHE_MISS__') {
            return $value;
        }

        $value = $callback();
        self::forever($key, $value);

        return $value;
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function forget(string $key): bool
    {
        return self::driver()->forget($key);
    }

    public static function flush(): bool
    {
        return self::driver()->flush();
    }

    public static function increment(string $key, int $by = 1): int
    {
        return self::driver()->increment($key, $by);
    }

    public static function decrement(string $key, int $by = 1): int
    {
        return self::driver()->decrement($key, $by);
    }

    /**
     * Ambil lalu langsung hapus dari cache.
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * Buat testing/switching driver manual.
     */
    public static function setDriver(CacheDriverInterface $driver): void
    {
        self::$driver = $driver;
    }
}
