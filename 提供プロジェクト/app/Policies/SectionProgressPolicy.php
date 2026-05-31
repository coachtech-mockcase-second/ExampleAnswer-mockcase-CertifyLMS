<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\SectionProgress;
use App\Models\User;

/**
 * 受講生の Section 読了マーク(SectionProgress)に対する認可ポリシー。
 *
 * - viewAny / view: 自身の Enrollment 配下のみ参照可
 * - create: 受講生本人のみ(POST .../read で UPSERT)
 * - delete: 自身の SectionProgress のみ(DELETE .../read で SoftDelete)
 *
 * cascade visibility 検証(Section / Chapter / Part の Published 連鎖)と Enrollment 状態検証は
 * Action 側のデータ整合性ガードで扱う(Policy はリソース所有判定のみに専念)。
 */
class SectionProgressPolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id;
    }

    public function view(User $user, SectionProgress $progress): bool
    {
        $progress->loadMissing('enrollment');

        return $user->role === UserRole::Student
            && $progress->enrollment !== null
            && $progress->enrollment->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function delete(User $user, SectionProgress $progress): bool
    {
        return $this->view($user, $progress);
    }
}
