<?php
declare(strict_types=1);

namespace Flint\Mail;

interface DriverInterface
{
    public function send(MailMessage $message): void;
}
