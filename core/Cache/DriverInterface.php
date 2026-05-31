<?php
declare(strict_types=1);

namespace Flint\Cache;

interface DriverInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl): bool;
    public function forever(string $key, mixed $value): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function has(string $key): bool;
}
