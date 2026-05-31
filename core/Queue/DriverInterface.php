<?php
declare(strict_types=1);

namespace Flint\Queue;

interface DriverInterface
{
    public function push(Job $job, string $queue, int $delay = 0): void;
    public function claim(string $queue): ?array;
    public function ack(array $jobRecord): void;
    public function fail(array $jobRecord, \Throwable $e): void;
    public function release(array $jobRecord, int $delay): void;
}
