<?php
declare(strict_types=1);

namespace Flint;

/**
 * QueryBuilder that hydrates results into Model instances.
 */
class ModelQueryBuilder extends QueryBuilder
{
    public function __construct(string $table, private readonly string $modelClass)
    {
        parent::__construct($table);
    }

    /** Execute and return an array of model instances converted to arrays. */
    public function get(): array
    {
        $rows = parent::get();
        return array_map(fn($row) => (new ($this->modelClass)($row))->toArray(), $rows);
    }

    /** Return the first result as a model instance or null. */
    public function first(): ?array
    {
        $rows = $this->limit(1)->get();
        return $rows[0] ?? null;
    }

    /** Return a model instance rather than an array. */
    public function firstModel(): ?object
    {
        $rows = parent::get();
        $row = $rows[0] ?? null;
        return $row ? new ($this->modelClass)($row) : null;
    }
}
