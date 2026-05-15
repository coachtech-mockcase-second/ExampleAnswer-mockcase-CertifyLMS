<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;

class ChapterPolicy
{
    public function viewAny(User $auth, Part $part): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $part->certification),
            default => false,
        };
    }

    public function view(User $auth, Chapter $chapter): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $chapter->part->certification),
            default => $chapter->status === ContentStatus::Published,
        };
    }

    public function create(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
    }

    public function update(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function delete(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function publish(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function unpublish(User $auth, Chapter $chapter): bool
    {
        return $this->canManage($auth, $chapter->part->certification);
    }

    public function reorder(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
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
