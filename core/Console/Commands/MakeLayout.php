<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeLayout extends Command
{
    public function signature(): string
    {
        return 'make:layout';
    }

    public function description(): string
    {
        return 'Create a new Spark layout file';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php flint make:layout <name>');
            $this->line('  Example: php flint make:layout app');
            exit(1);
        }

        $fullPath = $this->app->basePath . '/resources/views/layouts/' . $name . '.spark.php';
        $dir      = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->warn("Layout already exists: resources/views/layouts/{$name}.spark.php");
            return;
        }

        $stub = <<<'EMBER'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Flint' }}</title>
</head>
<body>
    @yield('content')
</body>
</html>
EMBER;

        file_put_contents($fullPath, $stub . "\n");
        $this->info("Layout created: resources/views/layouts/{$name}.spark.php");
    }
}
