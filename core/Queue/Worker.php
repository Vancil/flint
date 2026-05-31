<?php
declare(strict_types=1);

namespace Flint\Queue;

class Worker
{
    private bool $shouldStop = false;

    public function __construct(private readonly DriverInterface $driver) {}

    /** Start the worker loop. */
    public function work(string $queue = 'default', int $sleep = 1): void
    {
        $this->registerSignalHandlers();

        while (!$this->shouldStop) {
            $jobRecord = $this->driver->claim($queue);

            if (!$jobRecord) {
                $this->maybePcntlDispatch();
                sleep($sleep);
                continue;
            }

            $job = unserialize($jobRecord['job'] ?? $jobRecord['payload']);
            $jobRecord['attempts'] = ($jobRecord['attempts'] ?? 0) + 1;

            $jobClass = get_class($job);
            $this->output("Processing: {$jobClass}");

            try {
                $job->handle();
                $this->driver->ack($jobRecord);
                $this->output("\033[32mProcessed:  {$jobClass}\033[0m");
            } catch (\Throwable $e) {
                $this->handleFailure($job, $jobRecord, $e);
            }

            $this->maybePcntlDispatch();
        }
    }

    private function handleFailure(Job $job, array $jobRecord, \Throwable $e): void
    {
        $jobClass = get_class($job);
        $this->output("\033[31mFailed:     {$jobClass} — " . $e->getMessage() . "\033[0m");
        fwrite(STDERR, "[{$jobRecord['queue']}] {$jobClass} failed: " . $e->getMessage() . "\n");

        if ($jobRecord['attempts'] < $job->tries) {
            $this->driver->release($jobRecord, $job->retryAfter);
        } else {
            $job->failed($e);
            $this->driver->fail($jobRecord, $e);
        }
    }

    private function output(string $message): void
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }

    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, function (): void { $this->shouldStop = true; });
        pcntl_signal(SIGINT,  function (): void { $this->shouldStop = true; });
    }

    private function maybePcntlDispatch(): void
    {
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }
}
