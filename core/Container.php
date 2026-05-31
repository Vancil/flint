<?php
declare(strict_types=1);

namespace Flint;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];

    /** Bind an abstract to a factory or concrete class. */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /** Bind as a singleton — resolved once and reused. */
    public function singleton(string $abstract, Closure|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    /** Store an already-constructed instance. */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /** Resolve an abstract from the container. */
    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $instance = $this->build($this->singletons[$abstract]);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->build($this->bindings[$abstract]);
        }

        return $this->build($abstract);
    }

    private function build(Closure|string $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (!class_exists($concrete)) {
            throw new \RuntimeException("Cannot resolve [{$concrete}]: class does not exist.");
        }

        $ref = new ReflectionClass($concrete);

        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Cannot instantiate [{$concrete}].");
        }

        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new \RuntimeException(
                    "Cannot resolve primitive dependency \${$param->getName()} in [{$concrete}]."
                );
            }
        }

        return $ref->newInstanceArgs($dependencies);
    }
}
