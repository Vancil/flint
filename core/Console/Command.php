<?php
declare(strict_types=1);

namespace Flint\Console;

use Flint\Application;

abstract class Command
{
    public function __construct(protected readonly Application $app) {}

    abstract public function signature(): string;
    abstract public function description(): string;
    abstract public function handle(array $args): void;

    protected function info(string $msg): void
    {
        echo "\033[32m{$msg}\033[0m\n";
    }

    protected function warn(string $msg): void
    {
        echo "\033[33m{$msg}\033[0m\n";
    }

    protected function error(string $msg): void
    {
        echo "\033[31m{$msg}\033[0m\n";
    }

    protected function line(string $msg): void
    {
        echo $msg . "\n";
    }
}
