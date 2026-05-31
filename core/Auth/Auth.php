<?php
declare(strict_types=1);

namespace Flint\Auth;

use Flint\Request;
use Flint\Session;

class Auth
{
    private ?object $resolvedUser = null;

    public function __construct(private readonly Session $session) {}

    /** Log a user in, regenerating the session to prevent fixation. */
    public function login(object $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->set('_auth_id', $user->id);
        $this->session->set('_auth_guard', get_class($user));
        $this->resolvedUser = $user;

        if ($remember) {
            $token = bin2hex(random_bytes(30));
            $hash  = hash('sha256', $token);

            // Store the hash on the user model via the ORM
            $user->remember_token = $hash;
            $user->save();

            $secure = (bool) (config('session.secure', false));
            setcookie('remember_me', $token, [
                'expires'  => time() + 86400 * 30,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /** Log the current user out, regenerating the session. */
    public function logout(): void
    {
        // Clear remember-me cookie if present
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }

        $this->session->regenerate();
        $this->session->forget('_auth_id');
        $this->session->forget('_auth_guard');
        $this->resolvedUser = null;
    }

    /** Get the currently authenticated user, or null. */
    public function user(): ?object
    {
        if ($this->resolvedUser !== null) {
            return $this->resolvedUser;
        }

        $id    = $this->session->get('_auth_id');
        $guard = $this->session->get('_auth_guard');

        if ($id === null || $guard === null || !class_exists($guard)) {
            return null;
        }

        /** @var class-string $guard */
        $this->resolvedUser = $guard::find((int) $id);
        return $this->resolvedUser;
    }

    /** Whether a user is currently authenticated. */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** The authenticated user's ID, or null. */
    public function id(): int|string|null
    {
        return $this->session->get('_auth_id');
    }

    /** Whether there is no authenticated user (guest). */
    public function guest(): bool
    {
        return !$this->check();
    }

    /** Attempt to authenticate via the remember-me cookie. */
    public function viaRemember(Request $request): void
    {
        $token = $_COOKIE['remember_me'] ?? null;
        if ($token === null) {
            return;
        }

        $guard = config('auth.model', \App\Models\User::class);
        if (!class_exists($guard)) {
            return;
        }

        $hash = hash('sha256', $token);
        $user = $guard::where('remember_token', $hash)->firstModel();
        if ($user) {
            $this->login($user, true);
        }
    }
}
