<?php
declare(strict_types=1);

namespace Flint\Auth;

use Closure;
use Flint\Request;
use Flint\Response;

class AuthMiddleware
{
    public function __construct(private readonly Auth $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->auth->check()) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
