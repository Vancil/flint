<?php
declare(strict_types=1);

namespace Flint;

use Flint\Exceptions\CsrfTokenMismatchException;

class Csrf
{
    public function __construct(private readonly Session $session) {}

    public function token(): string
    {
        if (!$this->session->has('_csrf_token')) {
            $this->session->set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return $this->session->get('_csrf_token');
    }

    public function tokenField(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Verify the CSRF token on state-changing requests. Throws on mismatch. */
    public function verify(Request $request): void
    {
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];
        if (in_array($request->method(), $safeMethods, true)) {
            return;
        }

        $token = $request->input('_token') ?? $request->header('X-CSRF-Token');

        if (!$token || !hash_equals($this->token(), (string) $token)) {
            throw new CsrfTokenMismatchException('CSRF token mismatch.');
        }
    }
}
