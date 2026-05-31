<?php
declare(strict_types=1);

namespace Tests\Unit;

use Flint\Container;
use Flint\Pipeline;
use Flint\Request;
use Flint\Response;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function test_passes_request_to_destination(): void
    {
        $container = new Container();
        $request   = $this->makeRequest();

        $response = (new Pipeline($container))
            ->send($request)
            ->through([])
            ->then(fn(Request $req) => Response::json(['hit' => true]));

        $this->assertSame('{"hit":true}', $this->getBody($response));
    }

    public function test_middleware_runs_in_order(): void
    {
        $container = new Container();
        $log = new \stdClass();
        $log->entries = [];

        $container->bind(StubMiddlewareA::class, fn() => new StubMiddlewareA($log));
        $container->bind(StubMiddlewareB::class, fn() => new StubMiddlewareB($log));

        (new Pipeline($container))
            ->send($this->makeRequest())
            ->through([StubMiddlewareA::class, StubMiddlewareB::class])
            ->then(function (Request $req) use ($log) {
                $log->entries[] = 'destination';
                return Response::noContent();
            });

        $this->assertSame(['A', 'B', 'destination'], $log->entries);
    }

    public function test_middleware_can_short_circuit(): void
    {
        $container = new Container();
        $container->bind(StubBlockingMiddleware::class, fn() => new StubBlockingMiddleware());

        $response = (new Pipeline($container))
            ->send($this->makeRequest())
            ->through([StubBlockingMiddleware::class])
            ->then(fn() => Response::json(['reached' => true]));

        $this->assertSame(401, $this->getStatus($response));
    }

    private function makeRequest(): Request
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/';
        return new Request();
    }

    private function getBody(Response $response): string
    {
        return (new \ReflectionProperty($response, 'body'))->getValue($response);
    }

    private function getStatus(Response $response): int
    {
        return (new \ReflectionProperty($response, 'status'))->getValue($response);
    }
}

// Stubs

class StubMiddlewareA
{
    public function __construct(private \stdClass $log) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $this->log->entries[] = 'A';
        return $next($request);
    }
}

class StubMiddlewareB
{
    public function __construct(private \stdClass $log) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $this->log->entries[] = 'B';
        return $next($request);
    }
}

class StubBlockingMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        return Response::json(['error' => 'Unauthorized'], 401);
    }
}
