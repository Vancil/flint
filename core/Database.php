<?php
declare(strict_types=1);

namespace Flint;

class Database
{
    private static ?\PDO $instance = null;

    /** Get the singleton PDO connection. */
    public static function connection(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = config('database');
        $driver = $cfg['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            self::$instance = new \PDO("sqlite:{$cfg['database']}");
        } elseif ($driver === 'mysql') {
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";
            self::$instance = new \PDO($dsn, $cfg['username'], $cfg['password']);
        } else {
            throw new \RuntimeException("Unsupported DB driver: {$driver}");
        }

        self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        return self::$instance;
    }

    /** Replace the connection (useful in tests). */
    public static function setConnection(\PDO $pdo): void
    {
        self::$instance = $pdo;
    }
}
