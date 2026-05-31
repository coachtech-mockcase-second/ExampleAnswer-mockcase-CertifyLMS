<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CoachAvailability;
use App\Models\User;

/**
 * 面談可能時間枠 (CoachAvailability) リソースに対する認可ポリシー。
 *
 * 編集 UI 自体はプロフィール設定画面が `/settings/availability` 配下で所有するが、
 * 「自分の枠だけ作成 / 更新 / 削除できる」というロール固有判定は本 Policy で集約する。
 *
 * 受講生も予約画面で他コーチの枠を閲覧する権利を持つため viewAny / view は全ロール true、
 * 作成 / 更新 / 削除は本人(role=coach かつ coach_id === user.id)に限定。
 */
class CoachAvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, CoachAvailability $availability): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Coach;
    }

    public function update(User $user, CoachAvailability $availability): bool
    {
        return $user->role === UserRole::Coach
            && $availability->coach_id === $user->id;
    }

    public function delete(User $user, CoachAvailability $availability): bool
    {
        return $this->update($user, $availability);
    }
}
