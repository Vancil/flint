<?php
declare(strict_types=1);

namespace Flint;

class Response
{
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {}

    /** JSON response. */
    public static function json(mixed $data, int $status = 200): static
    {
        $r = new static(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status);
        return $r->withHeader('Content-Type', 'application/json');
    }

    /** HTML response. */
    public static function html(string $content, int $status = 200): static
    {
        $r = new static($content, $status);
        return $r->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /** Plain text response. */
    public static function text(string $content, int $status = 200): static
    {
        $r = new static($content, $status);
        return $r->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Redirect response. */
    public static function redirect(string $url, int $status = 302): static
    {
        $r = new static('', $status);
        return $r->withHeader('Location', $url);
    }

    /** 204 No Content. */
    public static function noContent(): static
    {
        return new static('', 204);
    }

    /** Add or replace a response header. */
    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /** Override the HTTP status code. */
    public function withStatus(int $status): static
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    /** Send headers and body, then terminate. */
    public function send(): never
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
        exit;
    }
}
