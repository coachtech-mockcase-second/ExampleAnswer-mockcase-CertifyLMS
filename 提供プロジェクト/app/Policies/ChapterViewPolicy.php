<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\User;

/**
 * 受講生視点での Chapter 閲覧可否を判定する Policy。教材管理側の `ChapterPolicy` (admin / coach 用) とは
 * 別 Gate (`learning.chapter.view`) で登録する。
 *
 * 判定: 親 Part の Certification を `learning` または `passed` 状態で受講登録しているか。
 */
class ChapterViewPolicy
{
    public function view(User $user, Chapter $chapter): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        $chapter->loadMissing('part');
        $part = $chapter->part;

        if ($part === null) {
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
