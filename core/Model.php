<?php
declare(strict_types=1);

namespace Flint;

use Flint\Exceptions\ModelNotFoundException;

abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $casts = [];
    protected bool $timestamps = true;

    private array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /** Find a record by primary key. */
    public static function find(int|string $id): ?static
    {
        $instance = new static();
        $row = (new QueryBuilder($instance->table))
            ->where($instance->primaryKey, $id)
            ->first();
        return $row ? $instance->hydrate($row) : null;
    }

    /** Find or throw ModelNotFoundException. */
    public static function findOrFail(int|string $id): static
    {
        return static::find($id) ?? throw new ModelNotFoundException(static::class, $id);
    }

    /** Return all records. */
    public static function all(): array
    {
        $instance = new static();
        $rows = (new QueryBuilder($instance->table))->get();
        return array_map(fn($row) => $instance->hydrate($row)->toArray(), $rows);
    }

    /** Start a QueryBuilder chain filtered on this model's table. */
    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): ModelQueryBuilder
    {
        $instance = new static();
        $qb = new ModelQueryBuilder($instance->table, static::class);
        return $qb->where($column, $operatorOrValue, $value);
    }

    /** Return the first record or null. */
    public static function first(): ?static
    {
        $instance = new static();
        $row = (new QueryBuilder($instance->table))->first();
        return $row ? $instance->hydrate($row) : null;
    }

    /** Insert a new record and return the model instance. */
    public static function create(array $data): static
    {
        $instance = new static();
        $filtered = $instance->filterFillable($data);

        if ($instance->timestamps) {
            $now = date('Y-m-d H:i:s');
            $filtered['created_at'] = $now;
            $filtered['updated_at'] = $now;
        }

        $id = (new QueryBuilder($instance->table))->insert($filtered);
        return static::findOrFail($id);
    }

    /** Save the current model (INSERT or UPDATE). */
    public function save(): bool
    {
        $data = $this->attributes;

        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if (isset($this->attributes[$this->primaryKey])) {
            return (new QueryBuilder($this->table))
                ->where($this->primaryKey, $this->attributes[$this->primaryKey])
                ->update($data);
        }

        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        $id = (new QueryBuilder($this->table))->insert($data);
        $this->attributes[$this->primaryKey] = $id;
        return true;
    }

    /** Update attributes and save. */
    public function update(array $data): bool
    {
        $filtered = $this->filterFillable($data);
        foreach ($filtered as $k => $v) {
            $this->attributes[$k] = $v;
        }
        return $this->save();
    }

    /**
     * One-to-many: this model's primary key appears as a foreign key on the related table.
     * Returns an array of related model arrays.
     *
     * Usage: $user->hasMany(Post::class)
     */
    protected function hasMany(string $related, ?string $foreignKey = null, string $localKey = 'id'): array
    {
        $foreignKey ??= $this->inferForeignKey(static::class);
        $rel = new $related();
        $rows = (new QueryBuilder($rel->table))
            ->where($foreignKey, $this->attributes[$localKey])
            ->get();
        return array_map(fn($row) => (new $related($row))->toArray(), $rows);
    }

    /**
     * One-to-one: this model's primary key appears as a foreign key on the related table.
     * Returns a single related model instance or null.
     *
     * Usage: $user->hasOne(Profile::class)
     */
    protected function hasOne(string $related, ?string $foreignKey = null, string $localKey = 'id'): ?object
    {
        $foreignKey ??= $this->inferForeignKey(static::class);
        $rel = new $related();
        $row = (new QueryBuilder($rel->table))
            ->where($foreignKey, $this->attributes[$localKey])
            ->first();
        return $row ? new $related($row) : null;
    }

    /**
     * Inverse: this model holds the foreign key pointing to the related model.
     * Returns a single related model instance or null.
     *
     * Usage: $post->belongsTo(User::class)
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, string $ownerKey = 'id'): ?object
    {
        $foreignKey ??= $this->inferForeignKey($related);
        $rel = new $related();
        $row = (new QueryBuilder($rel->table))
            ->where($ownerKey, $this->attributes[$foreignKey])
            ->first();
        return $row ? new $related($row) : null;
    }

    /** Derive a snake_case foreign key from a fully-qualified class name. e.g. App\Models\BlogPost → blog_post_id */
    private function inferForeignKey(string $class): string
    {
        $base = basename(str_replace('\\', '/', $class));
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base));
        return $snake . '_id';
    }

    /** Delete this record. */
    public function delete(): bool
    {
        return (new QueryBuilder($this->table))
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->delete();
    }

    /** Convert to array, excluding hidden fields and applying casts. */
    public function toArray(): array
    {
        $data = $this->attributes;
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        return $this->applyCasts($data);
    }

    /** JSON-encode the model. */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function hydrate(array $row): static
    {
        $instance = new static();
        $instance->attributes = $this->applyCasts($row);
        return $instance;
    }

    private function applyCasts(array $data): array
    {
        foreach ($this->casts as $field => $type) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = match ($type) {
                'bool', 'boolean' => (bool) $data[$field],
                'int', 'integer'  => (int) $data[$field],
                'float'           => (float) $data[$field],
                'string'          => (string) $data[$field],
                'array'           => is_string($data[$field]) ? json_decode($data[$field], true) : $data[$field],
                default           => $data[$field],
            };
        }
        return $data;
    }

    private function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
