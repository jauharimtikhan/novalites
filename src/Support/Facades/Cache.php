<?php

namespace Novalites\Support\Facades;

use Novalites\Support\Facade;
use Novalites\Cache\Cache as CacheManager;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $ttl = 3600)
 * @method static bool forever(string $key, mixed $value)
 * @method static mixed remember(string $key, int $ttl, \Closure $callback)
 * @method static bool forget(string $key)
 * @method static bool flush()
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CacheManager::class;
    }
}
