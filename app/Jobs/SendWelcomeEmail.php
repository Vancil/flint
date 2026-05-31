<?php
declare(strict_types=1);

namespace App\Jobs;

use Flint\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private readonly int $userId,
        private readonly string $email,
    ) {}

    public function handle(): void
    {
        // Send welcome email to $this->email for user $this->userId.
        // Integrate your mailer here.
        error_log("Welcome email dispatched to {$this->email} (user #{$this->userId})");
    }
}
