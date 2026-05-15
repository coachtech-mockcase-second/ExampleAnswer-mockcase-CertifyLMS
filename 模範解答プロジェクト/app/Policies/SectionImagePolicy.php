<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;

class SectionImagePolicy
{
    public function create(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function delete(User $auth, SectionImage $image): bool
    {
        return $this->canManage($auth, $image->section->chapter->part->certification);
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
