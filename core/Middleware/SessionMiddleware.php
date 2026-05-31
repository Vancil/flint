<?php
declare(strict_types=1);

namespace Flint\Middleware;

use Closure;
use Flint\Auth\Auth;
use Flint\Request;
use Flint\Response;
use Flint\Session;

class SessionMiddleware
{
    public function __construct(
        private readonly Session $session,
        private readonly Auth $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->session->start();
        $this->session->set('_previous_url', $request->uri());

        // Auto-login via remember-me cookie if no active session auth
        if (!$this->auth->check()) {
            $this->auth->viaRemember($request);
        }

        return $next($request);
    }
}
