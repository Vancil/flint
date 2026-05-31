<?php
declare(strict_types=1);

namespace Flint;

class ColumnDefinition
{
    public bool $nullable = false;
    public mixed $defaultValue = null;
    public bool $hasDefault = false;
    public bool $isUnique = false;
    public bool $isUnsigned = false;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $options = [],
    ) {}

    public function nullable(): static
    {
        $this->nullable = true;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function unique(): static
    {
        $this->isUnique = true;
        return $this;
    }

    public function unsigned(): static
    {
        $this->isUnsigned = true;
        return $this;
    }
}
