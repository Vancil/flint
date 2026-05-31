<?php
declare(strict_types=1);

namespace Flint\Mail;

class PendingMail
{
    private string $subject  = '';
    private string $htmlBody = '';
    private string $textBody = '';

    public function __construct(
        private readonly DriverInterface $driver,
        private readonly string $to,
        private readonly string $toName = '',
    ) {}

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $body): static
    {
        $this->htmlBody = $body;
        return $this;
    }

    public function text(string $body): static
    {
        $this->textBody = $body;
        return $this;
    }

    /** Render an Ember view as the HTML body. */
    public function view(string $emberView, array $data = []): static
    {
        if (isset($GLOBALS['__flint_app'])) {
            $engine = $GLOBALS['__flint_app']->make(\Flint\View\EmberEngine::class);
            $this->htmlBody = $engine->render($emberView, $data);
        }
        return $this;
    }

    public function send(): void
    {
        $from     = config('mail.from.address', 'hello@example.com');
        $fromName = config('mail.from.name', 'Flint');

        $message = new MailMessage(
            to:       $this->to,
            toName:   $this->toName,
            subject:  $this->subject,
            htmlBody: $this->htmlBody,
            textBody: $this->textBody,
            from:     $from,
            fromName: $fromName,
        );

        $this->driver->send($message);
    }
}
