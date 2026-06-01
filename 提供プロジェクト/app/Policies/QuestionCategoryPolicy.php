<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\User;

/**
 * 出題分野マスタの認可ポリシー。
 *
 * - admin: 全資格配下を CRUD 可
 * - coach: 担当資格配下のみ CRUD 可
 * 演習問題と模試問題の両系統から参照される共有マスタの管理権限を制御する。
 */
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
            UserRole::Coach => false,
            default => false,
        };
    }
}
