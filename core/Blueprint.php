<?php
declare(strict_types=1);

namespace Flint;

class Blueprint
{
    /** @var ColumnDefinition[] */
    private array $columns = [];

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn($name, 'id');
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, 'string', ['length' => $length]);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function longText(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'longText');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'bigInteger');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function float(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'float');
    }

    public function decimal(string $name, int $total = 8, int $places = 2): ColumnDefinition
    {
        return $this->addColumn($name, 'decimal', ['total' => $total, 'places' => $places]);
    }

    public function timestamp(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'timestamp');
    }

    /** Add created_at and updated_at timestamp columns. */
    public function timestamps(): void
    {
        $this->addColumn('created_at', 'timestamp')->nullable();
        $this->addColumn('updated_at', 'timestamp')->nullable();
    }

    /** Add a deleted_at column for soft deletes. */
    public function softDeletes(): void
    {
        $this->addColumn('deleted_at', 'timestamp')->nullable();
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    /** Generate a CREATE TABLE statement. */
    public function toCreateSql(string $table, string $driver): string
    {
        $parts = [];
        $uniqueKeys = [];

        foreach ($this->columns as $col) {
            $parts[] = $this->columnSql($col, $driver);
            if ($col->isUnique) {
                $uniqueKeys[] = "UNIQUE (`{$col->name}`)";
            }
        }

        $all = implode(",\n    ", array_merge($parts, $uniqueKeys));
        return "CREATE TABLE `{$table}` (\n    {$all}\n)";
    }

    /** Generate ALTER TABLE ADD COLUMN statements. */
    public function toAlterSql(string $table, string $driver): array
    {
        $sqls = [];
        foreach ($this->columns as $col) {
            $sqls[] = "ALTER TABLE `{$table}` ADD COLUMN " . $this->columnSql($col, $driver);
            if ($col->isUnique) {
                $sqls[] = "ALTER TABLE `{$table}` ADD UNIQUE (`{$col->name}`)";
            }
        }
        return $sqls;
    }

    private function addColumn(string $name, string $type, array $options = []): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type, $options);
        $this->columns[] = $col;
        return $col;
    }

    private function columnSql(ColumnDefinition $col, string $driver): string
    {
        $type = $this->resolveType($col, $driver);

        // id columns carry their own constraints inline
        if ($col->type === 'id') {
            return "`{$col->name}` {$type}";
        }

        $sql = "`{$col->name}` {$type}";

        if ($col->isUnsigned && $driver !== 'sqlite') {
            $sql .= ' UNSIGNED';
        }

        $sql .= $col->nullable ? ' NULL' : ' NOT NULL';

        if ($col->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($col->defaultValue);
        }

        return $sql;
    }

    private function resolveType(ColumnDefinition $col, string $driver): string
    {
        if ($driver === 'sqlite') {
            return match ($col->type) {
                'id'         => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'bigInteger' => 'INTEGER',
                'integer'    => 'INTEGER',
                'boolean'    => 'INTEGER',
                'float'      => 'REAL',
                'decimal'    => 'REAL',
                'timestamp'  => 'DATETIME',
                default      => 'TEXT',
            };
        }

        $length = $col->options['length'] ?? 255;
        $total  = $col->options['total'] ?? 8;
        $places = $col->options['places'] ?? 2;

        return match ($col->type) {
            'id'         => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            'string'     => "VARCHAR({$length})",
            'text'       => 'TEXT',
            'longText'   => 'LONGTEXT',
            'integer'    => 'INT',
            'bigInteger' => 'BIGINT',
            'boolean'    => 'TINYINT(1)',
            'float'      => 'FLOAT',
            'decimal'    => "DECIMAL({$total},{$places})",
            'timestamp'  => 'TIMESTAMP',
            'json'       => 'JSON',
            default      => 'VARCHAR(255)',
        };
    }

    private function formatDefault(mixed $value): string
    {
        return match (true) {
            is_null($value)   => 'NULL',
            is_bool($value)   => $value ? '1' : '0',
            is_string($value) => "'{$value}'",
            default           => (string) $value,
        };
    }
}
