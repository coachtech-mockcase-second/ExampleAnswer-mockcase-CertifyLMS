<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 受講開始 / プラン延長時の初期付与記録ユースケース。
 *
 * `User.max_meetings` カラムの UPDATE は呼出側責務(オンボーディング / プラン延長 Action)。
 * 本 Action は `MeetingQuotaTransaction(type=granted_initial)` の INSERT のみを担い、
 * 残数集計と整合した監査ログとして付与履歴を残す。
 */
final class GrantInitialQuotaAction
{
    /**
     * @param  User  $user  対象受講生
     * @param  int  $amount  付与回数(正の整数)
     * @param  ?User  $admin  admin 経由のプラン延長時の操作 admin、システム自動付与時は NULL
     * @param  ?string  $reason  監査ログ用の理由文字列
     *
     * @throws InvalidArgumentException 付与回数が 0 以下の場合
     */
    public function __invoke(
        User $user,
        int $amount,
        ?User $admin = null,
        ?string $reason = null,
    ): MeetingQuotaTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('付与する面談回数は正の整数で指定してください。');
        }

        return DB::transaction(fn () => MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::GrantedInitial,
            'amount' => $amount,
            'granted_by_user_id' => $admin?->id,
            'note' => $reason,
            'occurred_at' => now(),
        ]));
    }
}
