<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class CertificationCoachAssignmentPolicy
{
    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
