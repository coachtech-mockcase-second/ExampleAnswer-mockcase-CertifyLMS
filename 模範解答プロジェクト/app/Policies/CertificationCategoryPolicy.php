<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CertificationCategory;
use App\Models\User;

class CertificationCategoryPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, CertificationCategory $category): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, CertificationCategory $category): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
