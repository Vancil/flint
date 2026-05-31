<?php
declare(strict_types=1);

namespace Tests\Unit;

use Flint\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_json_encodes_array(): void
    {
        $response = Response::json(['key' => 'value']);
        $this->assertSame('{"key":"value"}', $this->getBody($response));
    }

    public function test_json_sets_content_type_header(): void
    {
        $response = Response::json([]);
        $this->assertSame('application/json', $this->getHeader($response, 'Content-Type'));
    }

    public function test_json_default_status_is_200(): void
    {
        $response = Response::json([]);
        $this->assertSame(200, $this->getStatus($response));
    }

    public function test_json_accepts_custom_status(): void
    {
        $response = Response::json([], 201);
        $this->assertSame(201, $this->getStatus($response));
    }

    public function test_with_status_overrides_status(): void
    {
        $response = Response::json([])->withStatus(422);
        $this->assertSame(422, $this->getStatus($response));
    }

    public function test_with_header_adds_header(): void
    {
        $response = Response::json([])->withHeader('X-Foo', 'bar');
        $this->assertSame('bar', $this->getHeader($response, 'X-Foo'));
    }

    public function test_with_status_does_not_mutate_original(): void
    {
        $original = Response::json([]);
        $modified = $original->withStatus(404);
        $this->assertSame(200, $this->getStatus($original));
        $this->assertSame(404, $this->getStatus($modified));
    }

    public function test_no_content_returns_204(): void
    {
        $response = Response::noContent();
        $this->assertSame(204, $this->getStatus($response));
    }

    public function test_redirect_sets_location_header(): void
    {
        $response = Response::redirect('/login');
        $this->assertSame('/login', $this->getHeader($response, 'Location'));
        $this->assertSame(302, $this->getStatus($response));
    }

    public function test_html_sets_content_type(): void
    {
        $response = Response::html('<p>hello</p>');
        $this->assertStringContainsString('text/html', $this->getHeader($response, 'Content-Type'));
    }

    public function test_text_sets_content_type(): void
    {
        $response = Response::text('hello');
        $this->assertStringContainsString('text/plain', $this->getHeader($response, 'Content-Type'));
    }

    public function test_json_encodes_nested_structures(): void
    {
        $response = Response::json(['user' => ['id' => 1, 'name' => 'Dan']]);
        $this->assertSame('{"user":{"id":1,"name":"Dan"}}', $this->getBody($response));
    }

    // Reflection helpers to inspect private Response state without calling send()

    private function getBody(Response $response): string
    {
        return (new \ReflectionProperty($response, 'body'))->getValue($response);
    }

    private function getStatus(Response $response): int
    {
        return (new \ReflectionProperty($response, 'status'))->getValue($response);
    }

    private function getHeader(Response $response, string $name): ?string
    {
        $headers = (new \ReflectionProperty($response, 'headers'))->getValue($response);
        return $headers[$name] ?? null;
    }
}
