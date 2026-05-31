<?php
declare(strict_types=1);

namespace Flint\Queue;

class RedisDriver implements DriverInterface
{
    private \Redis $redis;

    public function __construct(private readonly array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('The redis PHP extension is required to use the Redis queue driver.');
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

    public function push(Job $job, string $queue, int $delay = 0): void
    {
        $payload = json_encode([
            'job'          => serialize($job),
            'attempts'     => 0,
            'available_at' => time() + $delay,
            'queue'        => $queue,
        ]);

        if ($delay > 0) {
            // Use a sorted set for delayed jobs; score = available_at timestamp
            $this->redis->zAdd("flint_delayed:{$queue}", time() + $delay, $payload);
        } else {
            $this->redis->lPush("flint_queue:{$queue}", $payload);
        }
    }

    public function claim(string $queue): ?array
    {
        // Promote any due delayed jobs first
        $this->promoteDelayed($queue);

        $payload = $this->redis->brPoplPush("flint_queue:{$queue}", "flint_queue:{$queue}:processing", 2);

        if (!$payload) {
            return null;
        }

        $data = json_decode($payload, true);
        $data['_raw'] = $payload;
        return $data;
    }

    public function ack(array $jobRecord): void
    {
        $this->redis->lRem("flint_queue:{$jobRecord['queue']}:processing", $jobRecord['_raw'], 1);
    }

    public function fail(array $jobRecord, \Throwable $e): void
    {
        $this->redis->lRem("flint_queue:{$jobRecord['queue']}:processing", $jobRecord['_raw'], 1);
        $this->redis->lPush('flint_failed', json_encode([
            'queue'     => $jobRecord['queue'],
            'payload'   => $jobRecord['_raw'],
            'exception' => (string) $e,
            'failed_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function release(array $jobRecord, int $delay): void
    {
        $this->redis->lRem("flint_queue:{$jobRecord['queue']}:processing", $jobRecord['_raw'], 1);

        $jobRecord['attempts'] = ($jobRecord['attempts'] ?? 0);
        unset($jobRecord['_raw']);

        $payload = json_encode($jobRecord);

        if ($delay > 0) {
            $this->redis->zAdd("flint_delayed:{$jobRecord['queue']}", time() + $delay, $payload);
        } else {
            $this->redis->lPush("flint_queue:{$jobRecord['queue']}", $payload);
        }
    }

    private function promoteDelayed(string $queue): void
    {
        $due = $this->redis->zRangeByScore("flint_delayed:{$queue}", '-inf', (string) time());
        foreach ($due as $payload) {
            $this->redis->zRem("flint_delayed:{$queue}", $payload);
            $this->redis->lPush("flint_queue:{$queue}", $payload);
        }
    }
}
