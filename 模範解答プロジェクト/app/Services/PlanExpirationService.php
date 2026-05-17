<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Plan 期限切れ判定 + 残日数算出を提供するステートレス Service。
 * 受講生ダッシュボードのプラン情報パネルと、期限満了 Schedule Command の事前判定から利用される。
 */
final class PlanExpirationService
{
    /**
     * Plan の期限が既に過去かを判定する。plan_expires_at が NULL の場合は false(未設定 = 期限切れではない扱い)。
     */
    public function isExpired(User $user): bool
    {
        return $user->plan_expires_at !== null && $user->plan_expires_at->isPast();
    }

    /**
     * Plan の残日数を返す。plan_expires_at が NULL の場合は -1(未設定 = 期限なし)を返す。
     * 期限切れの場合は 0 を返す(負数は返さない)。
     *
     * 端数日(例: 残り 1 日 13 時間)は切り上げて 2 を返す。
     * Carbon の diffInDays は整数を返すため、float 比較できる floatDiffInDays を用いて ceil する。
     */
    public function daysRemaining(User $user): int
    {
        if ($user->plan_expires_at === null) {
            return -1;
        }

        $diff = (int) ceil(now()->floatDiffInDays($user->plan_expires_at, false));

        return max(0, $diff);
    }
}
