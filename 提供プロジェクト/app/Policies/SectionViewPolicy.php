<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\Section;
use App\Models\User;

/**
 * 受講生視点での Section 閲覧可否を判定する Policy。教材管理側の `SectionPolicy` (admin / coach 用) とは
 * 別 Gate (`learning.section.view`) で登録する。
 *
 * 判定: 親 Chapter → 親 Part の Certification を `learning` または `passed` 状態で受講登録しているか。
 * cascade visibility(Section / Chapter / Part が全て Published かつ SoftDelete されていない)の検証は
 * BrowseController / Action 側で 404 として扱う(Policy は登録資格の所有判定のみ)。
 */
class SectionViewPolicy
{
    public function view(User $user, Section $section): bool
    {
        if ($user->role !== UserRole::Student) {
            return false;
        }

        $section->loadMissing('chapter.part');
        $part = $section->chapter?->part;

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
