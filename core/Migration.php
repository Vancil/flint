<?php
declare(strict_types=1);

namespace Flint;

class Migration
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /** Run all pending migrations. Returns number of migrations run. */
    public function migrate(): int
    {
        $this->ensureMigrationsTable();
        $ran = $this->getRanMigrations();
        $files = $this->getMigrationFiles();
        $batch = $this->getNextBatch();
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = require $file;
            $migration->up($this->pdo);

            $stmt = $this->pdo->prepare(
                'INSERT INTO migrations (migration, batch, ran_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $batch, date('Y-m-d H:i:s')]);
            echo "\033[32m  Migrated:\033[0m {$name}\n";
            $count++;
        }

        return $count;
    }

    /** Roll back the last batch of migrations. Returns number rolled back. */
    public function rollback(): int
    {
        $this->ensureMigrationsTable();
        $batch = $this->getCurrentBatch();

        if ($batch === 0) {
            return 0;
        }

        $stmt = $this->pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC');
        $stmt->execute([$batch]);
        $migrations = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $count = 0;

        foreach ($migrations as $name) {
            $file = $this->getMigrationsPath() . '/' . $name;
            if (file_exists($file)) {
                $migration = require $file;
                $migration->down($this->pdo);
            }

            $del = $this->pdo->prepare('DELETE FROM migrations WHERE migration = ?');
            $del->execute([$name]);
            echo "\033[33m  Rolled back:\033[0m {$name}\n";
            $count++;
        }

        return $count;
    }

    private function ensureMigrationsTable(): void
    {
        $driver = config('database.driver', 'mysql');

        if ($driver === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL,
                ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )';
        }

        $this->pdo->exec($sql);
    }

    private function getRanMigrations(): array
    {
        return $this->pdo->query('SELECT migration FROM migrations')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->getMigrationsPath() . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function getMigrationsPath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH . '/database/migrations' : getcwd() . '/database/migrations';
    }

    private function getNextBatch(): int
    {
        return $this->getCurrentBatch() + 1;
    }

    private function getCurrentBatch(): int
    {
        $result = $this->pdo->query('SELECT MAX(batch) FROM migrations')->fetchColumn();
        return $result !== null ? (int) $result : 0;
    }
}
