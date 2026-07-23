<?php

namespace Workbench\App\Listeners;

use Workbench\App\Events\PostCreated;

class SendPostNotification
{
    public function handle(PostCreated $event): void
    {
        logger('New post created: '.$event->post->title);
    }
}
