<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function updateRole(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function withdraw(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
