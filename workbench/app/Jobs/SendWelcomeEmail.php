<?php

namespace Workbench\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Workbench\App\Models\User;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        logger('Sending welcome email to: '.$this->user->email);
    }
}
