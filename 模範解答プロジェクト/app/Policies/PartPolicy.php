<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Part;
use App\Models\User;

class PartPolicy
{
    public function viewAny(User $auth, Certification $certification): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $certification),
            default => false,
        };
    }

    public function view(User $auth, Part $part): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $part->certification),
            default => $part->status === ContentStatus::Published,
        };
    }

    public function create(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
    }

    public function update(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
    }

    public function delete(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
    }

    public function publish(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
    }

    public function unpublish(User $auth, Part $part): bool
    {
        return $this->canManage($auth, $part->certification);
    }

    public function reorder(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
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
