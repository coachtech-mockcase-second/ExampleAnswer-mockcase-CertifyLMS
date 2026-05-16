<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\User;

class CertificationPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Certification $certification): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        return $certification->status === CertificationStatus::Published
            && $certification->deleted_at === null;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, Certification $certification): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
