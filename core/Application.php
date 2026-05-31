<?php
declare(strict_types=1);

namespace Flint;

use Dotenv\Dotenv;
use Flint\Auth\Auth;
use Flint\Auth\AuthMiddleware;
use Flint\Mail\Drivers\LogDriver;
use Flint\Mail\Drivers\SmtpDriver;
use Flint\Mail\Mailer;
use Flint\Middleware\CorsMiddleware;
use Flint\Middleware\CsrfMiddleware;
use Flint\Middleware\SessionMiddleware;
use Flint\View\EmberEngine;

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

        // Make the application globally accessible for static helpers
        $GLOBALS['__flint_app'] = $this;

        $this->container = new Container();
        $this->router    = new Router();
        $this->request   = new Request();

        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(Request::class, $this->request);
        $this->container->instance(Application::class, $this);

        $this->registerSingletons();
        $this->registerMiddlewareAliases();
        $this->loadPackages();
        $this->loadRoutes();
    }

    /** Handle the incoming HTTP request. */
    public function handleRequest(): void
    {
        $globalMiddleware = config('app.middleware', [
            SessionMiddleware::class,
            CsrfMiddleware::class,
        ]);

        $response = (new Pipeline($this->container))
            ->send($this->request)
            ->through($globalMiddleware)
            ->then(fn(Request $req) => $this->router->dispatch($req, $this->container));

        $response->send();
    }

    /** Resolve something from the container. */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    private function registerSingletons(): void
    {
        $basePath = $this->basePath;

        $this->container->singleton(Session::class, function () {
            return new Session(
                cookieName: config('session.cookie_name', 'flint_session'),
                lifetime:   (int) config('session.lifetime', 7200),
                path:       config('session.path', '/'),
                sameSite:   config('session.same_site', 'Lax'),
                secure:     (bool) config('session.secure', false),
            );
        });

        $this->container->singleton(Csrf::class, function ($c) {
            return new Csrf($c->make(Session::class));
        });

        $this->container->singleton(Auth::class, function ($c) {
            return new Auth($c->make(Session::class));
        });

        $this->container->singleton(EmberEngine::class, function () use ($basePath) {
            return new EmberEngine(
                viewsPath: $basePath . '/resources/views',
                cachePath: $basePath . '/storage/views',
            );
        });

        $this->container->singleton(Mailer::class, function () use ($basePath) {
            $driver = match (config('mail.driver', 'log')) {
                'smtp' => new SmtpDriver(
                    host:       config('mail.host', 'smtp.mailtrap.io'),
                    port:       (int) config('mail.port', 587),
                    username:   config('mail.username', ''),
                    password:   config('mail.password', ''),
                    encryption: config('mail.encryption', 'tls'),
                ),
                default => new LogDriver($basePath . '/storage/logs/mail.log'),
            };
            return new Mailer($driver);
        });
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
            'cors'    => CorsMiddleware::class,
            'session' => SessionMiddleware::class,
            'csrf'    => CsrfMiddleware::class,
            'auth'    => AuthMiddleware::class,
        ]);
    }
}
