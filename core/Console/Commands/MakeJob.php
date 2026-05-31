<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeJob extends Command
{
    public function signature(): string { return 'make:job'; }
    public function description(): string { return 'Scaffold a new queue job class'; }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: php flint make:job <Name>');
            exit(1);
        }

        $className = str_ends_with($name, 'Job') ? $name : $name . 'Job';
        $path = $this->app->basePath . "/app/Jobs/{$className}.php";

        if (file_exists($path)) {
            $this->warn("{$className} already exists.");
            return;
        }

        $stub = <<<PHP
<?php
declare(strict_types=1);

namespace App\Jobs;

use Flint\Queue\Job;

class {$className} extends Job
{
    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        //
    }
}
PHP;

        file_put_contents($path, $stub);
        $this->info("Job created: app/Jobs/{$className}.php");
    }
}
