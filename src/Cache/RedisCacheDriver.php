<?php

namespace Novalites\Cache;

use Predis\Client;

class RedisCacheDriver implements CacheDriverInterface
{
    protected Client $redis;
    protected string $prefix;

    public function __construct(array $config = [])
    {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => $config['host'] ?? '127.0.0.1',
            'port'   => $config['port'] ?? 6379,
            'password' => $config['password'] ?? null,
            'database' => $config['database'] ?? 0,
        ]);

        $this->prefix = $config['prefix'] ?? 'jtech_cache:';
    }

    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->key($key));

        if ($value === null) {
            return $default;
        }

        return $this->unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = $this->serialize($value);

        if ($ttl > 0) {
            $this->redis->setex($this->key($key), $ttl, $serialized);
        } else {
            $this->redis->set($this->key($key), $serialized);
        }

        return true;
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->key($key)) > 0;
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->key($key)) > 0;
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
        return true;
    }

    public function increment(string $key, int $by = 1): int
    {
        return (int) $this->redis->incrby($this->key($key), $by);
    }

    public function decrement(string $key, int $by = 1): int
    {
        return (int) $this->redis->decrby($this->key($key), $by);
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
