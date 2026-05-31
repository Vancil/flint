<?php
declare(strict_types=1);

namespace Flint\Queue;

class Queue
{
    private static ?DriverInterface $driver = null;

    /** Dispatch a job onto the queue. */
    public static function dispatch(Job $job, ?string $queue = 'default'): void
    {
        $delay = $job->delay ?? 0;
        static::driver()->push($job, $queue ?? 'default', $delay);
    }

    /** Dispatch a job after a delay. */
    public static function later(int $delaySeconds, Job $job, ?string $queue = 'default'): void
    {
        static::driver()->push($job, $queue ?? 'default', $delaySeconds);
    }

    private static function driver(): DriverInterface
    {
        if (static::$driver !== null) {
            return static::$driver;
        }

        $cfg = config('queue');
        static::$driver = match ($cfg['driver'] ?? 'database') {
            'redis'    => new RedisDriver($cfg['redis']),
            'database' => new DatabaseDriver(),
            default    => throw new \RuntimeException("Unsupported queue driver: {$cfg['driver']}"),
        };

        return static::$driver;
    }

    /** Override the driver (useful in tests). */
    public static function setDriver(DriverInterface $driver): void
    {
        static::$driver = $driver;
    }
}
