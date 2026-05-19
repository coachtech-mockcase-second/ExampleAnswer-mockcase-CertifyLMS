<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 管理者による面談回数の手動付与ユースケース。
 *
 * 受講生詳細画面からトラブル補填 / キャンペーン付与目的で発火される。
 * `granted_by_user_id` に操作 admin の ID を必須記録し、監査ログとして残す。
 * 残数集計には反映されるが `User.max_meetings` は更新しない(購入と同じ扱い)。
 */
final class AdminGrantQuotaAction
{
    /**
     * @param User $target 付与対象の受講生
     * @param int $amount 付与回数(正の整数)
     * @param User $admin 操作 admin
     * @param ?string $reason 付与理由(監査ログ)
     *
     * @throws InvalidArgumentException 付与回数が 0 以下の場合
     */
    public function __invoke(
        User $target,
        int $amount,
        User $admin,
        ?string $reason = null,
    ): MeetingQuotaTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('付与する面談回数は正の整数で指定してください。');
        }

        return DB::transaction(fn () => MeetingQuotaTransaction::create([
            'user_id' => $target->id,
            'type' => MeetingQuotaTransactionType::AdminGrant,
            'amount' => $amount,
            'granted_by_user_id' => $admin->id,
            'note' => $reason,
            'occurred_at' => now(),
        ]));
    }
}
