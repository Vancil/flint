<?php
declare(strict_types=1);

namespace Flint;

use Flint\Exceptions\ValidationException;

class Request
{
    private ?array $jsonBody = null;
    private array $merged = [];

    public function __construct()
    {
        $this->merged = array_merge($_GET, $_POST, $this->json());
    }

    /** HTTP method (uppercase). */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Request URI without query string. */
    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos !== false ? substr($uri, 0, $pos) : $uri;
    }

    /** Get a single input value from GET + POST + JSON body. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->merged[$key] ?? $default;
    }

    /** All input values merged (GET + POST + JSON body). */
    public function all(): array
    {
        return $this->merged;
    }

    /** Decoded JSON body. Returns [] if not JSON or empty. */
    public function json(): array
    {
        if ($this->jsonBody !== null) {
            return $this->jsonBody;
        }

        if (!$this->isJson()) {
            return $this->jsonBody = [];
        }

        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return $this->jsonBody = [];
        }

        $decoded = json_decode($raw, true);
        return $this->jsonBody = is_array($decoded) ? $decoded : [];
    }

    /** Check if a key is present in the merged input. */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->merged);
    }

    /** Get a request header value. */
    public function header(string $key): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$normalized] ?? $_SERVER[$key] ?? null;
    }

    /** Extract Bearer token from Authorization header. */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /** True if Content-Type is application/json. */
    public function isJson(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($ct, 'application/json');
    }

    /** Validate input against rules; returns validated data or throws ValidationException. */
    public function validate(array $rules): array
    {
        return (new Validator($this->all()))->validate($rules);
    }

    /** Get an uploaded file entry from $_FILES. */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /** Client IP address. */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
