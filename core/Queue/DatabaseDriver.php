<?php
declare(strict_types=1);

namespace Flint\Queue;

use Flint\Database;

class DatabaseDriver implements DriverInterface
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->ensureTable();
    }

    public function push(Job $job, string $queue, int $delay = 0): void
    {
        $availableAt = time() + $delay;
        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES (?, ?, 0, ?, ?)'
        );
        $stmt->execute([$queue, serialize($job), $availableAt, time()]);
    }

    public function claim(string $queue): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM jobs WHERE queue = ? AND available_at <= ? ORDER BY id ASC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$queue, time()]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }

            // Remove from jobs table atomically — worker owns it now
            $del = $this->pdo->prepare('DELETE FROM jobs WHERE id = ?');
            $del->execute([$row['id']]);

            $this->pdo->commit();
            return $row;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function ack(array $jobRecord): void
    {
        // Row was already deleted on claim.
    }

    public function fail(array $jobRecord, \Throwable $e): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO failed_jobs (queue, payload, exception, failed_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$jobRecord['queue'], $jobRecord['payload'], (string) $e]);
    }

    public function release(array $jobRecord, int $delay): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (queue, payload, attempts, available_at, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $jobRecord['queue'],
            $jobRecord['payload'],
            $jobRecord['attempts'],
            time() + $delay,
            time(),
        ]);
    }

    private function ensureTable(): void
    {
        $driver = config('database.driver', 'mysql');

        if ($driver === 'sqlite') {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(255) NOT NULL DEFAULT \'default\',
                payload LONGTEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                available_at INT NOT NULL,
                created_at INT NOT NULL
            )');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                exception LONGTEXT NOT NULL,
                failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        } else {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) NOT NULL DEFAULT \'default\',
                payload LONGTEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                available_at INT NOT NULL,
                created_at INT NOT NULL
            )');
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS failed_jobs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                exception LONGTEXT NOT NULL,
                failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )');
        }
    }
}
