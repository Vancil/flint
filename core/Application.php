<?php
declare(strict_types=1);

namespace Flint;

use Dotenv\Dotenv;

class Application
{
    private Container $container;
    private Router $router;
    private Request $request;

    public function __construct(public readonly string $basePath) {}

    /** Bootstrap the application. */
    public function boot(): void
    {
        $this->loadEnv();
        $this->validateConfig();

        $this->container = new Container();
        $this->router    = new Router();
        $this->request   = new Request();

        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Request::class, $this->request);
        $this->container->instance(Application::class, $this);

        $this->registerMiddlewareAliases();
        $this->loadPackages();
        $this->loadRoutes();
    }

    /** Handle the incoming HTTP request. */
    public function handleRequest(): void
    {
        $response = $this->router->dispatch($this->request, $this->container);
        $response->send();
    }

    /** Resolve something from the container. */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    private function validateConfig(): void
    {
        if (empty(env('APP_SECRET'))) {
            throw new \RuntimeException(
                'APP_SECRET is not set. Run `php flint key:generate` to create one.'
            );
        }
    }

    private function loadEnv(): void
    {
        if (file_exists($this->basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($this->basePath);
            $dotenv->load();
        }
    }

    private function loadPackages(): void
    {
        foreach (config('app.packages', []) as $package) {
            $package::register($this);
        }
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        $routesFile = $this->basePath . '/routes/web.php';
        if (file_exists($routesFile)) {
            require $routesFile;
        }
    }

    private function registerMiddlewareAliases(): void
    {
        $this->router->setMiddlewareAliases([
            'cors' => \Flint\Middleware\CorsMiddleware::class,
        ]);
    }
}
