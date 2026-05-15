<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\User;

class QuestionCategoryPolicy
{
    public function viewAny(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
    }

    public function create(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
    }

    public function update(User $auth, QuestionCategory $category): bool
    {
        return $this->canManage($auth, $category->certification);
    }

    public function delete(User $auth, QuestionCategory $category): bool
    {
        return $this->canManage($auth, $category->certification);
    }

    private function canManage(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $certification->coaches()->where('users.id', $auth->id)->exists(),
            default => false,
        };
    }
}
