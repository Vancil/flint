<?php
declare(strict_types=1);

namespace Flint;

class QueryBuilder
{
    private array $wheres = [];
    private ?string $orderBy = null;
    private ?int $limitVal = null;
    private ?int $offsetVal = null;
    private array $bindings = [];

    public function __construct(private readonly string $table) {}

    /** Add a WHERE clause. */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $this->wheres[] = "`{$column}` = ?";
            $this->bindings[] = $operatorOrValue;
        } else {
            $this->wheres[] = "`{$column}` {$operatorOrValue} ?";
            $this->bindings[] = $value;
        }
        return $this;
    }

    /** ORDER BY clause. */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy = "`{$column}` " . strtoupper($direction);
        return $this;
    }

    /** LIMIT clause. */
    public function limit(int $limit): static
    {
        $this->limitVal = $limit;
        return $this;
    }

    /** OFFSET clause. */
    public function offset(int $offset): static
    {
        $this->offsetVal = $offset;
        return $this;
    }

    /** Execute SELECT and return all rows as arrays. */
    public function get(): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $sql .= $this->buildWhere();
        if ($this->orderBy !== null) {
            $sql .= " ORDER BY {$this->orderBy}";
        }
        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }
        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Return the first matching row or null. */
    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** INSERT a row and return the new id. */
    public function insert(array $data): int|string
    {
        $cols = implode(', ', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$this->table}` ({$cols}) VALUES ({$placeholders})";

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->pdo()->lastInsertId();
    }

    /** UPDATE rows matching the current WHERE clauses. */
    public function update(array $data): bool
    {
        $sets = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $sql = "UPDATE `{$this->table}` SET {$sets}" . $this->buildWhere();

        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute([...array_values($data), ...$this->bindings]);
    }

    /** DELETE rows matching the current WHERE clauses. */
    public function delete(): bool
    {
        $sql = "DELETE FROM `{$this->table}`" . $this->buildWhere();
        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    private function pdo(): \PDO
    {
        return Database::connection();
    }
}
