<?php
declare(strict_types=1);

namespace Flint\Middleware;

use Closure;
use Flint\Csrf;
use Flint\Exceptions\CsrfTokenMismatchException;
use Flint\Request;
use Flint\Response;

class CsrfMiddleware
{
    /** URI patterns (with * wildcards) that are exempt from CSRF verification. */
    private array $except;

    public function __construct(private readonly Csrf $csrf)
    {
        $this->except = config('csrf.except', ['/api/*']);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isExcluded($request->uri())) {
            try {
                $this->csrf->verify($request);
            } catch (CsrfTokenMismatchException) {
                return Response::html('CSRF token mismatch.', 419);
            }
        }

        return $next($request);
    }

    private function isExcluded(string $uri): bool
    {
        foreach ($this->except as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $uri)) {
                return true;
            }
        }
        return false;
    }
}
