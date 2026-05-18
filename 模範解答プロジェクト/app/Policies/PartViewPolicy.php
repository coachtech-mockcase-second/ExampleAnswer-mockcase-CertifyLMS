<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Part;
use App\Models\User;

/**
 * 受講生視点での Part 閲覧可否を判定する Policy。教材管理側の `PartPolicy` (admin / coach 用) とは別 Gate
 * (`learning.part.view`) で登録し、認可ロジックを混在させない。
 *
 * 判定: 受講生が当該 Part の親 Certification を `learning` または `passed` 状態で受講登録しているか。
 * `passed` も復習として閲覧可(Plan 期間内であれば status 制限なし、`graduated` は `EnsureActiveLearning`
 * Middleware で route 段階で 403 になるため Policy では考慮しない)。
 */
class PartViewPolicy
{
    public function view(User $user, Part $part): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        return $user->enrollments()
            ->where('certification_id', $part->certification_id)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->exists();
    }
}
