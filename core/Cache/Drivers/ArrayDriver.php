<?php
declare(strict_types=1);

namespace Flint\Cache\Drivers;

use Flint\Cache\DriverInterface;

class ArrayDriver implements DriverInterface
{
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) {
            return $default;
        }

        ['e' => $expiry, 'v' => $value] = $this->store[$key];

        if ($expiry !== 0 && $expiry < time()) {
            unset($this->store[$key]);
            return $default;
        }

        return $value;
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        $this->store[$key] = ['e' => time() + $ttl, 'v' => $value];
        return true;
    }

    public function forever(string $key, mixed $value): bool
    {
        $this->store[$key] = ['e' => 0, 'v' => $value];
        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->store = [];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
