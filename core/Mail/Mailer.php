<?php
declare(strict_types=1);

namespace Flint\Mail;

class Mailer
{
    public function __construct(private readonly DriverInterface $driver) {}

    public function to(string $email, string $name = ''): PendingMail
    {
        return new PendingMail($this->driver, $email, $name);
    }
}
