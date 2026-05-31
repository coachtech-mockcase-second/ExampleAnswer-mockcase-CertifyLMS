<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LearningSession;
use App\Models\User;

/**
 * 学習セッション(LearningSession)に対する認可ポリシー。
 *
 * - viewAny / view: 受講生本人のセッションのみ参照可(`session.user_id = auth.id`)
 * - update: 同上(Schedule Command による auto-close 等の内部操作は Policy を介さない)
 *
 * 学習セッションの開始は BrowseController::showSection 内のサーバ側 auto-start に集約されるため、
 * `create` Policy は持たない(公開 HTTP エンドポイントとして start を提供しない)。
 */
class LearningSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function view(User $user, LearningSession $session): bool
    {
        return $user->role === UserRole::Student
            && $session->user_id === $user->id;
    }

    public function update(User $user, LearningSession $session): bool
    {
        return $this->view($user, $session);
    }
}
