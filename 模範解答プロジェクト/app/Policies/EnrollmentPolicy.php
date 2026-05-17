<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Enrollment リソースに対する認可ポリシー。
 *
 * - viewAny: admin / coach / student いずれかであれば一覧自体は閲覧可(取得スコープは Controller / Action 側で絞る)
 * - view: 受講生本人 / 当該資格の担当コーチ / admin
 * - create / delete: 受講生本人(自己登録 / 自己解除)。delete は status == Learning のみ
 * - receiveCertificate: 受講生本人 + status == Learning のみ
 * - resume: 受講生本人 + status == Failed、または admin
 * - admin 専用操作: viewAdmin / fail / updateExamDate
 *
 * coach の判定は certification.coaches リレーション(certification_coach_assignments 経由)で行う。
 */
class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $enrollment->user_id === $user->id,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
        };
    }

    public function viewAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function updateExamDate(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Admin
            && $enrollment->status !== EnrollmentStatus::Passed;
    }

    public function fail(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Admin
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    public function resume(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->status !== EnrollmentStatus::Failed) {
            return false;
        }

        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Student && $enrollment->user_id === $user->id);
    }

    public function receiveCertificate(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && $enrollment->status === EnrollmentStatus::Learning;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}
