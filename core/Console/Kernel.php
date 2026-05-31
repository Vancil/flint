<?php
declare(strict_types=1);

namespace Flint\Console;

use Flint\Application;
use Flint\Console\Commands\{
    MakeController,
    MakeModel,
    MakeJob,
    MakeMigration,
    Migrate,
    MigrateRollback,
    QueueWork,
    KeyGenerate
};

class Kernel
{
    private array $commands = [];

    public function __construct(private readonly Application $app)
    {
        $this->register([
            new KeyGenerate($app),
            new MakeController($app),
            new MakeModel($app),
            new MakeJob($app),
            new MakeMigration($app),
            new Migrate($app),
            new MigrateRollback($app),
            new QueueWork($app),
        ]);

        $this->loadPackageCommands();
    }

    /** Asks each registered package for CLI commands via an optional static commands() method. */
    private function loadPackageCommands(): void
    {
        foreach (config('app.packages', []) as $package) {
            if (method_exists($package, 'commands')) {
                $this->register($package::commands($this->app));
            }
        }
    }

    private function register(array $commands): void
    {
        foreach ($commands as $command) {
            $this->commands[$command->signature()] = $command;
        }
    }

    /** Parse $argv and dispatch to the matching command. */
    public function handle(array $argv): void
    {
        $name = $argv[1] ?? null;

        if ($name === null || $name === '--help' || $name === 'help') {
            $this->showHelp();
            return;
        }

        foreach ($this->commands as $signature => $command) {
            if ($signature === $name) {
                $command->handle(array_slice($argv, 2));
                return;
            }
        }

        $this->error("Unknown command: {$name}");
        $this->showHelp();
        exit(1);
    }

    private function showHelp(): void
    {
        $this->line("\033[33mFlint Framework CLI\033[0m");
        $this->line('');
        $this->line('Available commands:');
        foreach ($this->commands as $sig => $cmd) {
            printf("  \033[32m%-30s\033[0m %s\n", $sig, $cmd->description());
        }
    }

    private function line(string $msg): void
    {
        echo $msg . "\n";
    }

    private function error(string $msg): void
    {
        echo "\033[31m{$msg}\033[0m\n";
    }
}
