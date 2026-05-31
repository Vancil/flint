<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Cache\Cache;
use Flint\Console\Command;

class CacheClear extends Command
{
    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush all cached values.';
    }

    public function handle(array $args): void
    {
        $this->app->make(Cache::class)->flush();
        $this->info('Cache cleared.');
    }
}
