<?php
declare(strict_types=1);

namespace Flint\Cache;

use Closure;

class Cache
{
    public function __construct(private readonly DriverInterface $driver) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl ??= (int) config('cache.ttl', 3600);
        return $this->driver->put($key, $value, $ttl);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->driver->forever($key, $value);
    }

    public function forget(string $key): bool
    {
        return $this->driver->forget($key);
    }

    public function flush(): bool
    {
        return $this->driver->flush();
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /** Get a cached value, or compute and store it if missing. */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        $value = $this->driver->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    /** Get a cached value, or compute and store it forever if missing. */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        $value = $this->driver->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->driver->forever($key, $value);

        return $value;
    }

    /** Get a cached value and immediately remove it. */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->driver->get($key, $default);
        $this->driver->forget($key);

        return $value;
    }
}
