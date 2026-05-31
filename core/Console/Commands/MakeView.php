<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeView extends Command
{
    public function signature(): string
    {
        return 'make:view';
    }

    public function description(): string
    {
        return 'Create a new Ember view file';
    }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;

        if (!$name) {
            $this->error('Usage: php flint make:view <name>');
            $this->line('  Example: php flint make:view auth.login');
            exit(1);
        }

        $relativePath = str_replace('.', '/', $name) . '.ember';
        $fullPath     = $this->app->basePath . '/resources/views/' . $relativePath;
        $dir          = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($fullPath)) {
            $this->warn("View already exists: resources/views/{$relativePath}");
            return;
        }

        file_put_contents($fullPath, "@extends('layouts.app')\n\n@section('content')\n<h1>" . basename($name) . "</h1>\n@endsection\n");
        $this->info("View created: resources/views/{$relativePath}");
    }
}
