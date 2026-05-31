<?php
declare(strict_types=1);

namespace Flint\Mail;

class MailMessage
{
    public function __construct(
        public string $to        = '',
        public string $toName    = '',
        public string $subject   = '',
        public string $htmlBody  = '',
        public string $textBody  = '',
        public string $from      = '',
        public string $fromName  = '',
    ) {}
}
