<?php
declare(strict_types=1);

namespace Flint;

class Session
{
    private bool $started = false;

    public function __construct(
        private readonly string $cookieName = 'flint_session',
        private readonly int    $lifetime   = 7200,
        private readonly string $path       = '/',
        private readonly string $sameSite   = 'Lax',
        private readonly bool   $secure     = false,
    ) {}

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path'     => $this->path,
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => $this->sameSite,
        ]);

        session_name($this->cookieName);
        session_start();
        $this->started = true;

        // Promote outgoing flash data to incoming so it is available this request
        $_SESSION['_flash_incoming'] = $_SESSION['_flash_outgoing'] ?? [];
        $_SESSION['_flash_outgoing'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    /** Store a value that is available only on the next request. */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_outgoing'][$key] = $value;
    }

    /** Retrieve a value that was flashed on the previous request. */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_incoming'][$key] ?? $default;
    }

    /** Regenerate the session ID to prevent fixation attacks. */
    public function regenerate(): void
    {
        if ($this->started) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if ($this->started) {
            session_destroy();
            $_SESSION = [];
            $this->started = false;
        }
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function id(): string
    {
        return session_id();
    }
}
