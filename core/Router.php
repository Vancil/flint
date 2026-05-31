<?php
declare(strict_types=1);

namespace Flint;

use Closure;
use Flint\Exceptions\ValidationException;
use Flint\Exceptions\ModelNotFoundException;

class Router
{
    private array $routes = [];
    private array $groupStack = [];

    /** Register a middleware alias map set by Application. */
    private array $middlewareAliases = [];

    public function setMiddlewareAliases(array $aliases): void
    {
        $this->middlewareAliases = $aliases;
    }

    /** Merge additional aliases — used by packages to register without overwriting existing aliases. */
    public function addMiddlewareAlias(string $alias, string $class): void
    {
        $this->middlewareAliases[$alias] = $class;
    }

    public function get(string $uri, array|Closure $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, array|Closure $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, array|Closure $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, array|Closure $action): void
    {
        $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, array|Closure $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    /** Register a group of routes with shared attributes. */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $uri, array|Closure $action): void
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            $middleware = array_merge($middleware, $group['middleware'] ?? []);
        }

        $fullUri = $prefix . $uri;

        $this->routes[] = [
            'method'     => $method,
            'uri'        => $fullUri,
            'pattern'    => $this->buildPattern($fullUri),
            'action'     => $action,
            'middleware' => $middleware,
        ];
    }

    private function buildPattern(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /** Dispatch the request; return a Response. */
    public function dispatch(Request $request, Container $container): Response
    {
        $method = $request->method();
        $uri    = $request->uri();

        $methodMatches = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $methodMatches = true;
                continue;
            }

            // Extract named params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Resolve middleware class names
            $middlewareClasses = $this->resolveMiddleware($route['middleware']);

            // Build and execute pipeline
            try {
                return (new Pipeline($container))
                    ->send($request)
                    ->through($middlewareClasses)
                    ->then(function (Request $req) use ($route, $params, $container): Response {
                        return $this->callAction($route['action'], $params, $req, $container);
                    });
            } catch (\Flint\Exceptions\CsrfTokenMismatchException) {
                return Response::html('CSRF token mismatch.', 419);
            } catch (\Throwable $e) {
                if (config('app.debug')) {
                    return Response::json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
                }
                fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
                return Response::json(['error' => 'Server error.'], 500);
            }
        }

        if ($methodMatches) {
            return Response::json(['error' => 'Method Not Allowed.'], 405);
        }

        return Response::json(['error' => 'Not Found.'], 404);
    }

    private function callAction(array|Closure $action, array $params, Request $request, Container $container): Response
    {
        try {
            if ($action instanceof Closure) {
                return $action($request, ...array_values($params));
            }

            [$class, $method] = $action;
            $controller = $container->make($class);
            return $controller->$method(...$this->resolveMethodParams($class, $method, $params, $request, $container));
        } catch (ValidationException $e) {
            // In web mode (non-JSON request), redirect back with errors and old input
            if (!$request->isJson()) {
                return Response::back()
                    ->withErrors($e->errors())
                    ->withInput($request->all());
            }
            return Response::json(['errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return Response::json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            if (config('app.debug')) {
                return Response::json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
            }
            fwrite(STDERR, $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            return Response::json(['error' => 'Server error.'], 500);
        }
    }

    private function resolveMethodParams(string $class, string $method, array $routeParams, Request $request, Container $container): array
    {
        $ref = new \ReflectionMethod($class, $method);
        $resolved = [];

        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $resolved[] = $request;
            } elseif ($typeName && !$type->isBuiltin() && class_exists($typeName)) {
                $resolved[] = $container->make($typeName);
            } elseif (array_key_exists($param->getName(), $routeParams)) {
                $val = $routeParams[$param->getName()];
                $resolved[] = $typeName === 'int' ? (int) $val : $val;
            } elseif ($param->isDefaultValueAvailable()) {
                $resolved[] = $param->getDefaultValue();
            } else {
                $resolved[] = null;
            }
        }

        return $resolved;
    }

    private function resolveMiddleware(array $aliases): array
    {
        return array_map(function (string $alias): string {
            // Handle 'throttle:60,1' style config
            $name = explode(':', $alias)[0];
            return $this->middlewareAliases[$name] ?? $alias;
        }, $aliases);
    }
}
