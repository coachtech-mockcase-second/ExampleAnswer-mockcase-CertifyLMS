<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Models\User;

/**
 * 面談回数の初期付与ユースケース。受講開始 / プラン延長時に、プラン定義値ぶんの面談枠を付与する。
 *
 * 現状は MeetingQuotaTransaction を INSERT せず no-op で返す最小スタブで、プラン延長 / オンボーディングが
 * 統一シグネチャ `(User $user, int $amount, ?User $admin = null, ?string $reason = null)` で呼ぶための受け皿。
 * テストでは Mockery でモックされ、呼出回数 / 引数の検証のみ行う。
 *
 * TODO(meeting-quota): meeting-quota Feature 本実装時に、`MeetingQuotaTransaction(type=granted_initial,
 *                       amount=+$amount, granted_by_user_id=$admin?->id)` の INSERT を追加し、戻り値型を
 *                       MeetingQuotaTransaction に確定する。同タイミングで `final class` 化する
 *                       (現状 Mockery 互換性のため非 final にしている)。
 */
class GrantInitialQuotaAction
{
    public function __invoke(
        User $user,
        int $amount,
        ?User $admin = null,
        ?string $reason = null,
    ): mixed {
        // 本実装まで no-op。呼出側は Mockery で呼出引数を検証する。
        return null;
    }
}
