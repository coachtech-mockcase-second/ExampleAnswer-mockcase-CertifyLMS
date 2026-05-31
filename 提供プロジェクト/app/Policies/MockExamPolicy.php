<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\MockExam;
use App\Models\User;

/**
 * 模試マスタの認可ルール。
 *
 * - admin: 全資格配下の MockExam を CRUD + publish 可
 * - coach: 担当資格(certification_coach_assignments) 配下のみ CRUD + publish 可
 * - student: 受講中(learning) or 修了済(passed) の資格配下で `is_published = true` の模試を受験可
 *
 * 受講生の受験(`take`) は復習目的で `passed` でも許可する。
 */
class MockExamPolicy
{
    public function viewAny(User $auth): bool
    {
        return in_array($auth->role, [UserRole::Admin, UserRole::Coach], true);
    }

    public function view(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    public function create(User $auth, Certification $certification): bool
    {
        return $this->canManage($auth, $certification);
    }

    public function update(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    public function delete(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    public function publish(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    public function unpublish(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    public function manageQuestions(User $auth, MockExam $mockExam): bool
    {
        return $this->canManage($auth, $mockExam->certification);
    }

    /**
     * 受講生が公開模試を受験できる条件。
     *
     * - role = student
     * - mockExam.is_published = true
     * - 当該資格の enrollment が learning または passed
     */
    public function take(User $auth, MockExam $mockExam): bool
    {
        if ($auth->role !== UserRole::Student) {
            return false;
        }

        if (! $mockExam->is_published) {
            return false;
        }

        return $auth->enrollments()
            ->where('certification_id', $mockExam->certification_id)
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->exists();
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
