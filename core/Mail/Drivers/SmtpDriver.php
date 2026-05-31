<?php
declare(strict_types=1);

namespace Flint\Mail\Drivers;

use Flint\Mail\DriverInterface;
use Flint\Mail\MailMessage;

class SmtpDriver implements DriverInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port       = 587,
        private readonly string $username   = '',
        private readonly string $password   = '',
        private readonly string $encryption = 'tls',
    ) {}

    public function send(MailMessage $message): void
    {
        $socket = $this->connect();

        try {
            $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $this->expect($socket, 220);
            $this->command($socket, "EHLO {$hostname}");
            $this->read($socket);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command($socket, "EHLO {$hostname}");
                $this->read($socket);
            }

            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN');
                $this->expect($socket, 334);
                $this->command($socket, base64_encode($this->username));
                $this->expect($socket, 334);
                $this->command($socket, base64_encode($this->password));
                $this->expect($socket, 235);
            }

            $from = $message->from ?: config('mail.from.address', 'hello@example.com');
            $this->command($socket, "MAIL FROM:<{$from}>");
            $this->expect($socket, 250);

            $this->command($socket, "RCPT TO:<{$message->to}>");
            $this->expect($socket, 250);

            $this->command($socket, 'DATA');
            $this->expect($socket, 354);

            fwrite($socket, $this->buildRawMessage($message) . "\r\n.\r\n");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect(): mixed
    {
        $prefix = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = stream_socket_client(
            "{$prefix}{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        return $socket;
    }

    private function buildRawMessage(MailMessage $message): string
    {
        $boundary = md5(uniqid('', true));
        $from     = $message->from ?: config('mail.from.address', 'hello@example.com');
        $fromName = $message->fromName ?: config('mail.from.name', 'Flint');

        $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $headers .= "To: {$message->toName} <{$message->to}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($message->subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= ($message->textBody ?: strip_tags($message->htmlBody)) . "\r\n";

        if ($message->htmlBody !== '') {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $message->htmlBody . "\r\n";
        }

        $body .= "--{$boundary}--";

        return $headers . "\r\n" . $body;
    }

    private function command(mixed $socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    private function read(mixed $socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function expect(mixed $socket, int $code): void
    {
        $response = $this->read($socket);
        $actual   = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException("SMTP expected {$code}, got {$actual}: {$response}");
        }
    }
}
