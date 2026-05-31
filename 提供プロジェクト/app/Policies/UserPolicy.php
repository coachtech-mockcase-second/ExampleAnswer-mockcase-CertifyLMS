<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * 管理者向けユーザー運用画面の認可ルール + 本人によるプロフィール更新の認可ルール。
 *
 * 「他者のプロフィール / ロール変更」動線は本 LMS では提供しないため、`update` / `updateRole` は持たない。
 * 本人自身のプロフィール更新は `updateSelf` で扱い、admin による退会 / 期間延長 / 面談付与とは別軸で判定する。
 * メールアドレス変更 / ロール変更は退会 + 新規招待で運用する。
 */
class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function withdraw(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function extendCourse(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function grantMeetingQuota(User $auth, User $target): bool
    {
        return $auth->role === UserRole::Admin;
    }

    /**
     * プロフィール設定画面で本人が自分自身を編集する場合のみ true。
     * 他者を編集する admin 動線は本 LMS では提供しないため、ロール条件は問わず ID 一致のみで判定する。
     */
    public function updateSelf(User $auth, User $target): bool
    {
        return $auth->id === $target->id;
    }
}
