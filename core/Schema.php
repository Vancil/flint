<?php
declare(strict_types=1);

namespace Flint;

use Closure;

class Schema
{
    /** Create a new table. */
    public static function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        $sql = $blueprint->toCreateSql($table, static::driver());
        Database::connection()->exec($sql);
    }

    /** Modify an existing table (adds columns). */
    public static function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint();
        $callback($blueprint);
        foreach ($blueprint->toAlterSql($table, static::driver()) as $sql) {
            Database::connection()->exec($sql);
        }
    }

    /** Drop a table. */
    public static function drop(string $table): void
    {
        Database::connection()->exec("DROP TABLE `{$table}`");
    }

    /** Drop a table if it exists. */
    public static function dropIfExists(string $table): void
    {
        Database::connection()->exec("DROP TABLE IF EXISTS `{$table}`");
    }

    private static function driver(): string
    {
        return config('database.driver', 'mysql');
    }
}
