<?php
declare(strict_types=1);

namespace Flint\View;

class EmberEngine
{
    private EmberCompiler $compiler;

    public function __construct(
        private readonly string $viewsPath,
        private readonly string $cachePath,
    ) {
        $this->compiler = new EmberCompiler($viewsPath);
    }

    /** Render a view by dot-notation name, returning the HTML string. */
    public function render(string $view, array $data = []): string
    {
        $sourcePath = $this->resolve($view);
        $cachedPath = $this->cached($sourcePath);

        if ($this->isStale($sourcePath, $cachedPath)) {
            $this->recompile($sourcePath, $cachedPath);
        }

        return $this->evaluate($cachedPath, $data);
    }

    public function exists(string $view): bool
    {
        return file_exists($this->resolve($view));
    }

    /** Convert dot-notation to filesystem path. */
    private function resolve(string $view): string
    {
        $relativePath = str_replace('.', '/', $view) . '.ember';
        $path = $this->viewsPath . '/' . $relativePath;

        if (!file_exists($path)) {
            throw new \RuntimeException("Ember view not found: {$view} ({$path})");
        }

        return $path;
    }

    /** Determine cache file path for a given source path. */
    private function cached(string $sourcePath): string
    {
        return $this->cachePath . '/' . md5($sourcePath) . '.php';
    }

    /** Returns true if the cache file is missing or older than the source. */
    private function isStale(string $sourcePath, string $cachedPath): bool
    {
        if (!file_exists($cachedPath)) {
            return true;
        }

        // In production (APP_DEBUG=false), skip staleness check — OPcache handles it
        if (!config('app.debug', true)) {
            return false;
        }

        return filemtime($sourcePath) > filemtime($cachedPath);
    }

    private function recompile(string $sourcePath, string $cachedPath): void
    {
        $source   = file_get_contents($sourcePath);
        $compiled = $this->compiler->compile($source, $sourcePath);

        $dir = dirname($cachedPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cachedPath, $compiled);
    }

    /** Include the compiled template in an isolated scope and capture its output. */
    private function evaluate(string $cachedPath, array $data): string
    {
        // Make the engine available inside templates for @include
        $data['__ember'] = $this;

        // Auto-inject auth instance so @auth / @guest directives work
        if (!isset($data['auth']) && isset($GLOBALS['__flint_app'])) {
            $data['auth'] = $GLOBALS['__flint_app']->make(\Flint\Auth\Auth::class);
        }

        // Auto-inject errors and old input from the session flash
        if (!isset($data['errors']) && isset($GLOBALS['__flint_app'])) {
            $session = $GLOBALS['__flint_app']->make(\Flint\Session::class);
            $data['errors'] = $session->getFlash('_errors', []);
        }

        return (static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__path;
            return ob_get_clean();
        })($cachedPath, $data);
    }
}
