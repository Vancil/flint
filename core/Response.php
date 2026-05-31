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

    /** Redirect to the previous URL (stored in session). */
    public static function back(int $status = 302): static
    {
        $url = '/';
        if (isset($GLOBALS['__flint_app'])) {
            $url = $GLOBALS['__flint_app']->make(Session::class)->get('_previous_url', '/');
        }
        return static::redirect($url, $status);
    }

    /** Render an Ember view and return an HTML response. */
    public static function view(string $view, array $data = [], int $status = 200): static
    {
        $engine = $GLOBALS['__flint_app']->make(\Flint\View\EmberEngine::class);
        return static::html($engine->render($view, $data), $status);
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

    /** Flash old input to the session (for repopulating forms after redirect). */
    public function withInput(array $input = []): static
    {
        if (isset($GLOBALS['__flint_app'])) {
            $GLOBALS['__flint_app']->make(Session::class)->flash('_old_input', $input);
        }
        return $this;
    }

    /** Flash validation errors to the session. */
    public function withErrors(array $errors): static
    {
        if (isset($GLOBALS['__flint_app'])) {
            $GLOBALS['__flint_app']->make(Session::class)->flash('_errors', $errors);
        }
        return $this;
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
