<?php
declare(strict_types=1);

namespace Flint\Console\Commands;

use Flint\Console\Command;

class KeyGenerate extends Command
{
    public function signature(): string { return 'key:generate'; }
    public function description(): string { return 'Generate a secure APP_SECRET and write it to .env'; }

    public function handle(array $args): void
    {
        $key = bin2hex(random_bytes(32));
        $envPath = $this->app->basePath . '/.env';

        if (!file_exists($envPath)) {
            $this->error('.env file not found. Copy .env.example to .env first.');
            exit(1);
        }

        $contents = file_get_contents($envPath);

        if (str_contains($contents, 'APP_SECRET=')) {
            $contents = preg_replace('/^APP_SECRET=.*/m', "APP_SECRET={$key}", $contents);
        } else {
            $contents .= "\nAPP_SECRET={$key}";
        }

        file_put_contents($envPath, $contents);
        $this->info("APP_SECRET set successfully.");
        $this->line("  Key: {$key}");
    }
}
