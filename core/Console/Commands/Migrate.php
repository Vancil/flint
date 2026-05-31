<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;
use Flint\Migration;

class Migrate extends Command
{
    public function signature(): string { return 'migrate'; }
    public function description(): string { return 'Run all pending database migrations'; }

    public function handle(array $args): void
    {
        $migration = new Migration();
        $count = $migration->migrate();

        if ($count === 0) {
            $this->warn('Nothing to migrate.');
        } else {
            $this->info("Ran {$count} migration(s).");
        }
    }
}
