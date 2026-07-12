<?php

namespace Novalites\Cache;

interface CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    public function forever(string $key, mixed $value): bool;
    public function has(string $key): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function increment(string $key, int $by = 1): int;
    public function decrement(string $key, int $by = 1): int;
}
