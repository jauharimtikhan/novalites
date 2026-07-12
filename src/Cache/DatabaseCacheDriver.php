<?php

namespace Novalites\Cache;

use Illuminate\Database\Capsule\Manager as Capsule;
use Novalites\Cache\CacheDriverInterface;

class DatabaseCacheDriver implements CacheDriverInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = Capsule::table('cache')->where('key', $key)->first();

        if ($row === null) {
            return $default;
        }

        // Cek expired
        if ($row->expiration !== 0 && $row->expiration < time()) {
            $this->forget($key);
            return $default;
        }

        return $this->unserialize($row->value);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $expiration = $ttl > 0 ? time() + $ttl : 0; // 0 = ga pernah expired

        Capsule::table('cache')->updateOrInsert(
            ['key' => $key],
            [
                'value'      => $this->serialize($value),
                'expiration' => $expiration,
            ]
        );

        return true;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function has(string $key): bool
    {
        return $this->get($key, '__CACHE_MISS__') !== '__CACHE_MISS__';
    }

    public function forget(string $key): bool
    {
        return Capsule::table('cache')->where('key', $key)->delete() > 0;
    }

    public function flush(): bool
    {
        Capsule::table('cache')->truncate();
        return true;
    }

    public function increment(string $key, int $by = 1): int
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $by;
        $this->put($key, $new, $this->getRemainingTtl($key));
        return $new;
    }

    public function decrement(string $key, int $by = 1): int
    {
        return $this->increment($key, -$by);
    }

    protected function getRemainingTtl(string $key): int
    {
        $row = Capsule::table('cache')->where('key', $key)->first();

        if ($row === null || $row->expiration === 0) {
            return 0;
        }

        $remaining = $row->expiration - time();
        return max($remaining, 60); // minimal 60 detik biar ga expired instan
    }

    protected function serialize(mixed $value): string
    {
        return serialize($value);
    }

    protected function unserialize(string $value): mixed
    {
        return unserialize($value);
    }
}
