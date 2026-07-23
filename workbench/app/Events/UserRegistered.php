<?php

namespace Workbench\App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workbench\App\Models\User;

class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public User $user,
    ) {}
}
