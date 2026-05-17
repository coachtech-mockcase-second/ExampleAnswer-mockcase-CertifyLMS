<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証 PDF の DL 認可ルール。
 * admin: 全件 / coach: 担当資格分の修了証のみ / student: 本人発行分のみ。
 */
class CertificatePolicy
{
    public function download(User $auth, Certificate $certificate): bool
    {
        if ($auth->role === UserRole::Admin) {
            return true;
        }

        if ($auth->role === UserRole::Student) {
            return $certificate->user_id === $auth->id;
        }

        if ($auth->role === UserRole::Coach) {
            // Coach 判定で coaches リレーションを毎回 SELECT させないよう、loadMissing で 1 回のみ resolve
            $certificate->loadMissing('certification.coaches');

            return $certificate->certification?->coaches->contains('id', $auth->id) ?? false;
        }

        return false;
    }
}
