<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\MockExamSession;
use App\Models\User;

/**
 * 模試受験セッション(MockExamSession) の認可ポリシー。
 *
 * - 受験者本人(student): 自分のセッションを view / start / saveAnswer / submit / cancel 可
 * - admin: 全セッションを閲覧可(操作は student 本人のみ)
 * - coach: 担当資格(certification.coaches) のセッションを閲覧可
 */
class MockExamSessionPolicy
{
    public function view(User $auth, MockExamSession $session): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $session->mockExam->certification),
            UserRole::Student => $session->user_id === $auth->id,
        };
    }

    public function start(User $auth, MockExamSession $session): bool
    {
        return $auth->role === UserRole::Student && $session->user_id === $auth->id;
    }

    public function saveAnswer(User $auth, MockExamSession $session): bool
    {
        return $auth->role === UserRole::Student && $session->user_id === $auth->id;
    }

    public function submit(User $auth, MockExamSession $session): bool
    {
        return $auth->role === UserRole::Student && $session->user_id === $auth->id;
    }

    public function cancel(User $auth, MockExamSession $session): bool
    {
        return $auth->role === UserRole::Student && $session->user_id === $auth->id;
    }

    public function viewAdmin(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Admin, UserRole::Coach], true);
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
