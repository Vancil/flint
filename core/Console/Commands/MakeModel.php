<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class MakeModel extends Command
{
    public function signature(): string { return 'make:model'; }
    public function description(): string { return 'Scaffold a new model class'; }

    public function handle(array $args): void
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->error('Usage: php flint make:model <Name>');
            exit(1);
        }

        $path = $this->app->basePath . "/app/Models/{$name}.php";

        if (file_exists($path)) {
            $this->warn("{$name} model already exists.");
            return;
        }

        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';

        $stub = <<<PHP
<?php
declare(strict_types=1);

namespace App\Models;

use Flint\Model;

class {$name} extends Model
{
    protected string \$table = '{$table}';
    protected array \$fillable = [];
    protected array \$hidden = [];
    protected array \$casts = [];
}
PHP;

        file_put_contents($path, $stub);
        $this->info("Model created: app/Models/{$name}.php");
    }
}
