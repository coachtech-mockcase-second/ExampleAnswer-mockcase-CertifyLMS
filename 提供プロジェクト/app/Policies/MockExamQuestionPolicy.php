<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;

/**
 * 模試問題(MockExamQuestion) の認可ポリシー。
 *
 * 親 MockExam の管理権限 = 問題の管理権限。`manage(User, MockExam)` で集約判定する。
 * Controller の shallow ルートは `index` / `create` / `store` で親 MockExam を受け、
 * `show` / `edit` / `update` / `destroy` で MockExamQuestion を受けるため、両方の Policy method を提供する。
 */
class MockExamQuestionPolicy
{
    public function viewAny(User $auth, MockExam $mockExam): bool
    {
        return $this->manage($auth, $mockExam);
    }

    public function view(User $auth, MockExamQuestion $question): bool
    {
        return $this->manage($auth, $question->mockExam);
    }

    public function create(User $auth, MockExam $mockExam): bool
    {
        return $this->manage($auth, $mockExam);
    }

    public function update(User $auth, MockExamQuestion $question): bool
    {
        return $this->manage($auth, $question->mockExam);
    }

    public function delete(User $auth, MockExamQuestion $question): bool
    {
        return $this->manage($auth, $question->mockExam);
    }

    public function reorder(User $auth, MockExam $mockExam): bool
    {
        return $this->manage($auth, $mockExam);
    }

    public function manage(User $auth, MockExam $mockExam): bool
    {
        return match ($auth->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->assignedCoach($auth, $mockExam->certification),
            default => false,
        };
    }

    private function assignedCoach(User $coach, Certification $certification): bool
    {
        return $certification->coaches()->where('users.id', $coach->id)->exists();
    }
}
