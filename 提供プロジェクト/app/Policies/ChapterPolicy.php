<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;

/**
 * Chapter の認可ポリシー。
 *
 * - admin: 全資格配下を CRUD 可
 * - coach: 担当資格配下のみ CRUD 可
 * - student: Published 状態のみ閲覧可
 */
class ChapterPolicy
{
    public function viewAny(User $auth, Part $part): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => false,
            default => false,
        };
    }

    public function view(User $auth, Chapter $chapter): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => false,
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
            UserRole::Coach => false,
            default => false,
        };
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
