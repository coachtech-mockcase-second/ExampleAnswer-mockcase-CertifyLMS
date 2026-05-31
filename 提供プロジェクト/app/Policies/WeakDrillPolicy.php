<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\User;

/**
 * 苦手分野ドリル画面の認可ポリシー。
 *
 * 判定: 本人 Student + 自分の Enrollment + status が learning または passed。
 * `failed` 状態の Enrollment では弱点ドリルへの遷移は不可。
 */
class WeakDrillPolicy
{
    public function view(User $auth, Enrollment $enrollment): bool
    {
        if ($auth->role !== UserRole::Student) {
            return false;
        }

        if ($enrollment->user_id !== $auth->id) {
            return false;
        }

        return in_array(
            $enrollment->status,
            [EnrollmentStatus::Learning, EnrollmentStatus::Passed],
            true,
        );
    }
}
