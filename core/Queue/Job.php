<?php
declare(strict_types=1);

namespace Flint\Queue;

abstract class Job
{
    public int $tries = 3;
    public int $retryAfter = 60;
    public ?int $delay = null;

    abstract public function handle(): void;

    /** Called when all retries are exhausted. */
    public function failed(\Throwable $e): void {}
}
