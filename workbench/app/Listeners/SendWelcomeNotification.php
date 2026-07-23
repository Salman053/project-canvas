<?php

namespace Workbench\App\Listeners;

use Workbench\App\Events\UserRegistered;

class SendWelcomeNotification
{
    public function handle(UserRegistered $event): void
    {
        logger('Welcome to: '.$event->user->email);
    }
}
