<?php

namespace Workbench\App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Workbench\App\Models\Post;

class ProcessPost implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Post $post,
    ) {}

    public function handle(): void
    {
        logger('Processing post: '.$this->post->id);
    }
}
