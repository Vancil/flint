<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeMigration extends Command
{
    public function signature(): string { return 'make:migration'; }
    public function description(): string { return 'Scaffold a new migration file'; }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: php flint make:migration <name>');
            exit(1);
        }

        $timestamp = date('Y_m_d_His');
        $filename  = "{$timestamp}_{$name}.php";
        $path      = $this->app->basePath . "/database/migrations/{$filename}";

        $stub = <<<'PHP'
<?php

use Flint\Schema;
use Flint\Blueprint;

return new class {
    public function up(PDO $pdo): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(PDO $pdo): void
    {
        Schema::dropIfExists('table_name');
    }
};
PHP;

        file_put_contents($path, $stub);
        $this->info("Migration created: database/migrations/{$filename}");
    }
}
