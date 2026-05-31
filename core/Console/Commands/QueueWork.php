<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;
use Flint\Queue\DatabaseDriver;
use Flint\Queue\RedisDriver;
use Flint\Queue\Worker;

class QueueWork extends Command
{
    public function signature(): string { return 'queue:work'; }
    public function description(): string { return 'Start the queue worker'; }

    public function handle(array $args): void
    {
        $queue = 'default';
        $sleep = 1;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--queue=')) {
                $queue = substr($arg, 8);
            }
            if (str_starts_with($arg, '--sleep=')) {
                $sleep = (int) substr($arg, 8);
            }
        }

        $cfg = config('queue');
        $driver = match ($cfg['driver'] ?? 'database') {
            'redis'    => new RedisDriver($cfg['redis']),
            'database' => new DatabaseDriver(),
            default    => throw new \RuntimeException("Unknown queue driver: {$cfg['driver']}"),
        };

        $this->info("Queue worker started on [{$queue}]. Press Ctrl+C to stop.");
        (new Worker($driver))->work($queue, $sleep);
    }
}
