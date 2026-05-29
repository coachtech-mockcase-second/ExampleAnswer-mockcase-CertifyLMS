<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaThread リソースに対する認可ポリシー。ロール + 当事者 + 担当資格の三重判定。
 *
 * - viewAny: 全ロール許可 (取得スコープは Action 側で絞る)
 * - view: admin 全件 / coach は担当資格 / student は公開資格のみ
 * - create: student のみ (REQ-qa-board-021)
 * - update / resolve / unresolve: 投稿者本人のみ (admin であっても代行不可)
 * - delete: 投稿者本人 または admin (回答有無の状態ガードは DestroyAction の QaThreadHasRepliesException が担う)
 */
class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, QaThread $thread): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => in_array($thread->certification_id, $user->coachingCertificationIds(), true),
            UserRole::Student => $thread->certification?->status === CertificationStatus::Published,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Admin
            || $thread->user_id === $user->id;
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $thread->user_id === $user->id;
    }
}
