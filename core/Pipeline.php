<?php
declare(strict_types=1);

namespace Flint;

use Closure;

class Pipeline
{
    private Request $request;
    private array $middleware = [];

    public function __construct(private readonly Container $container) {}

    /** Set the request travelling through the pipeline. */
    public function send(Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    /** Set the middleware stack (class names). */
    public function through(array $middleware): static
    {
        $this->middleware = $middleware;
        return $this;
    }

    /** Execute the pipeline ending with the destination closure. */
    public function then(Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(Closure $carry, string $class) => function (Request $req) use ($carry, $class): Response {
                $mw = $this->container->make($class);
                return $mw->handle($req, $carry);
            },
            $destination
        );

        return $pipeline($this->request);
    }
}
