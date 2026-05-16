<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function revoke(User $user, Invitation $invitation): bool
    {
        return $user->role === UserRole::Admin
            && $invitation->status === InvitationStatus::Pending;
    }
}
