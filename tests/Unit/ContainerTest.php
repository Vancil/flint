<?php
declare(strict_types=1);

namespace Tests\Unit;

use Flint\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_make_resolves_concrete_class_automatically(): void
    {
        $result = $this->container->make(StubClassNoDeps::class);
        $this->assertInstanceOf(StubClassNoDeps::class, $result);
    }

    public function test_bind_resolves_via_closure(): void
    {
        $this->container->bind(StubClassNoDeps::class, fn() => new StubClassNoDeps());
        $result = $this->container->make(StubClassNoDeps::class);
        $this->assertInstanceOf(StubClassNoDeps::class, $result);
    }

    public function test_bind_returns_new_instance_each_time(): void
    {
        $this->container->bind(StubClassNoDeps::class, fn() => new StubClassNoDeps());
        $a = $this->container->make(StubClassNoDeps::class);
        $b = $this->container->make(StubClassNoDeps::class);
        $this->assertNotSame($a, $b);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $this->container->singleton(StubClassNoDeps::class, fn() => new StubClassNoDeps());
        $a = $this->container->make(StubClassNoDeps::class);
        $b = $this->container->make(StubClassNoDeps::class);
        $this->assertSame($a, $b);
    }

    public function test_instance_returns_stored_object(): void
    {
        $obj = new StubClassNoDeps();
        $this->container->instance(StubClassNoDeps::class, $obj);
        $this->assertSame($obj, $this->container->make(StubClassNoDeps::class));
    }

    public function test_auto_resolves_constructor_dependencies(): void
    {
        $result = $this->container->make(StubClassWithDep::class);
        $this->assertInstanceOf(StubClassWithDep::class, $result);
        $this->assertInstanceOf(StubClassNoDeps::class, $result->dep);
    }

    public function test_throws_on_unresolvable_primitive(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make(StubClassWithPrimitive::class);
    }

    public function test_throws_on_nonexistent_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->container->make('NonExistentClass');
    }
}

// Stubs

class StubClassNoDeps
{
    public string $value = 'hello';
}

class StubClassWithDep
{
    public function __construct(public readonly StubClassNoDeps $dep) {}
}

class StubClassWithPrimitive
{
    public function __construct(private readonly string $name) {}
}
