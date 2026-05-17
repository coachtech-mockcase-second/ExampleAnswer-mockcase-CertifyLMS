<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 受講生の個人目標(EnrollmentGoal)に対する認可ポリシー。
 *
 * - view: 当該 Enrollment にアクセスできるロール(本人 / 担当コーチ / admin)
 * - create / update / delete / markAchieved / unmarkAchieved: 受講生本人のみ
 *
 * coach / admin は閲覧専用とし、CRUD / 達成マークの介入はしない。
 */
class EnrollmentGoalPolicy
{
    public function __construct(private readonly EnrollmentPolicy $enrollmentPolicy) {}

    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $this->enrollmentPolicy->view($user, $enrollment);
    }

    public function view(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment.certification.coaches');

        return $this->enrollmentPolicy->view($user, $goal->enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id;
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownedByStudent($user, $goal);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownedByStudent($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownedByStudent($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownedByStudent($user, $goal);
    }

    private function ownedByStudent(User $user, EnrollmentGoal $goal): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        $goal->loadMissing('enrollment');

        return $goal->enrollment !== null
            && $goal->enrollment->user_id === $user->id;
    }
}
