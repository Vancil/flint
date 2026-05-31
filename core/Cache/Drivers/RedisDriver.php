<?php
declare(strict_types=1);

namespace Flint\Cache\Drivers;

use Flint\Cache\DriverInterface;

class RedisDriver implements DriverInterface
{
    private \Redis $redis;

    public function __construct(
        private readonly array $config,
        private readonly string $prefix = 'flint_',
    ) {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The redis PHP extension is required to use the Redis cache driver.');
        }

        $this->redis = new \Redis();
        $this->redis->connect($config['host'] ?? '127.0.0.1', $config['port'] ?? 6379);

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select((int) $config['database']);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return $this->redis->setex($this->prefix . $key, $ttl, serialize($value));
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->redis->set($this->prefix . $key, serialize($value));
    }

    public function forget(string $key): bool
    {
        $this->redis->del($this->prefix . $key);
        return true;
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');

        if (!empty($keys)) {
            $this->redis->del(...$keys);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }
}
