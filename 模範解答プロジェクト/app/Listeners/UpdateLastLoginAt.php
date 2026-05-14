<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->save();
        }
    }
}
