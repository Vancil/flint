<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;
use Flint\Migration;

class MigrateRollback extends Command
{
    public function signature(): string { return 'migrate:rollback'; }
    public function description(): string { return 'Roll back the last batch of migrations'; }

    public function handle(array $args): void
    {
        $migration = new Migration();
        $count = $migration->rollback();

        if ($count === 0) {
            $this->warn('Nothing to roll back.');
        } else {
            $this->info("Rolled back {$count} migration(s).");
        }
    }
}
