<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function viewAny(User $auth, Chapter $chapter): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $chapter->part->certification),
            default => false,
        };
    }

    public function view(User $auth, Section $section): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role === UserRole::Coach) {
            return $this->assignedCoach($auth, $section->chapter->part->certification);
        }

        return $section->status === ContentStatus::Published
            && $section->chapter->status === ContentStatus::Published
            && $section->chapter->part->status === ContentStatus::Published;
    }

    public function create(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function update(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function delete(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function publish(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function unpublish(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    public function reorder(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function preview(User $auth, Section $section): bool
    {
        return $this->canManage($auth, $section->chapter->part->certification);
    }

    private function canManage(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $certification),
            default => false,
        };
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
