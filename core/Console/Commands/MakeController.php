<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeController extends Command
{
    public function signature(): string { return 'make:controller'; }
    public function description(): string { return 'Scaffold a new controller class'; }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: php flint make:controller <Name>');
            exit(1);
        }

        $className = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        $path = $this->app->basePath . "/app/Controllers/{$className}.php";

        if (file_exists($path)) {
            $this->warn("{$className} already exists.");
            return;
        }

        $stub = <<<PHP
<?php
declare(strict_types=1);

namespace App\Controllers;

use Flint\Request;
use Flint\Response;

class {$className}
{
    public function index(Request \$request): Response
    {
        return Response::json(['message' => 'Hello from {$className}']);
    }
}
PHP;

        file_put_contents($path, $stub);
        $this->info("Controller created: app/Controllers/{$className}.php");
    }
}
