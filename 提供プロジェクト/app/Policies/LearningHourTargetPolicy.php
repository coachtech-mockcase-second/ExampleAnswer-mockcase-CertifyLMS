<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\User;

/**
 * 学習時間目標(LearningHourTarget)に対する認可ポリシー。
 *
 * - view: admin / coach は閲覧専用(dashboard / progress 連動)、student は本人の Enrollment のみ
 * - create / update / delete: 受講生本人のみ
 *
 * 引数は親 Enrollment を受け取る(Enrollment × LearningHourTarget は UNIQUE 制約で 1:1、
 * Enrollment が無ければ LearningHourTarget も存在しないため、親リソース単位で判定する)。
 */
class LearningHourTargetPolicy
{
    public function __construct(private readonly EnrollmentPolicy $enrollmentPolicy) {}

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $this->enrollmentPolicy->view($user, $enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id;
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $this->create($user, $enrollment);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->create($user, $enrollment);
    }
}
