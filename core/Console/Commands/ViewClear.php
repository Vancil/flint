<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class ViewClear extends Command
{
    public function signature(): string
    {
        return 'view:clear';
    }

    public function description(): string
    {
        return 'Clear all compiled Spark view cache files';
    }

    public function handle(array $args): void
    {
        $cachePath = $this->app->basePath . '/storage/views';

        if (!is_dir($cachePath)) {
            $this->info('View cache is already empty.');
            return;
        }

        $count = 0;
        foreach (glob($cachePath . '/*.php') as $file) {
            unlink($file);
            $count++;
        }

        $this->info("Cleared {$count} compiled view(s) from storage/views/");
    }
}
