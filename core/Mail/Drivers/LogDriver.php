<?php
declare(strict_types=1);

namespace Flint\Mail\Drivers;

use Flint\Mail\DriverInterface;
use Flint\Mail\MailMessage;

class LogDriver implements DriverInterface
{
    public function __construct(private readonly string $logPath) {}

    public function send(MailMessage $message): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = implode("\n", [
            str_repeat('-', 60),
            'Date: ' . date('Y-m-d H:i:s'),
            "To: {$message->toName} <{$message->to}>",
            "From: {$message->fromName} <{$message->from}>",
            "Subject: {$message->subject}",
            '',
            $message->textBody ?: strip_tags($message->htmlBody),
            '',
        ]);

        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}
