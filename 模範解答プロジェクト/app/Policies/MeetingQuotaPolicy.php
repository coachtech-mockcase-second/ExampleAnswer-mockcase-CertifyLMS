<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * 受講生向けの面談回数操作(購入 / 履歴閲覧)に関する認可。
 * SKU マスタ CRUD は MeetingQuotaPlanPolicy が担当する。
 */
class MeetingQuotaPolicy
{
    /**
     * 追加面談 SKU の購入が可能か。受講中(in_progress)の受講生のみ。
     */
    public function purchase(User $auth): bool
    {
        return $auth->role === UserRole::Student
            && $auth->status === UserStatus::InProgress;
    }

    /**
     * 面談回数履歴の閲覧。本人のみ可。
     */
    public function viewHistory(User $auth, User $target): bool
    {
        return $auth->id === $target->id;
    }
}
