<?php
declare(strict_types=1);

namespace Flint\Cache\Drivers;

use Flint\Cache\DriverInterface;

class FileDriver implements DriverInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $prefix = 'flint_',
    ) {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, recursive: true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->filepath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        if ($data['e'] !== 0 && $data['e'] < time()) {
            unlink($file);
            return $default;
        }

        return $data['v'];
    }

    public function put(string $key, mixed $value, int $ttl): bool
    {
        return file_put_contents(
            $this->filepath($key),
            serialize(['e' => time() + $ttl, 'v' => $value]),
            LOCK_EX,
        ) !== false;
    }

    public function forever(string $key, mixed $value): bool
    {
        return file_put_contents(
            $this->filepath($key),
            serialize(['e' => 0, 'v' => $value]),
            LOCK_EX,
        ) !== false;
    }

    public function forget(string $key): bool
    {
        $file = $this->filepath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function flush(): bool
    {
        foreach (glob($this->path . '/*.cache') as $file) {
            unlink($file);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function filepath(string $key): string
    {
        return $this->path . '/' . md5($this->prefix . $key) . '.cache';
    }
}
