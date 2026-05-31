<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 面談回数の残数集計と履歴取得を提供するステートレス Service。
 *
 * 残数集計は `User.max_meetings`(初期付与 + コース延長付与の累計) と
 * `MeetingQuotaTransaction.amount` の和で求める。`granted_initial` は `max_meetings` と
 * 二重カウント防止のため SUM 集計から除外する。
 *
 * 履歴取得は受講生の面談履歴画面と admin のユーザー詳細画面から呼ばれる想定で、
 * 関連の Meeting / Payment / 管理者を Eager Loading して N+1 を抑制する。
 */
final class MeetingQuotaService
{
    /**
     * 受講生の残面談回数を 1 クエリで集計する。
     */
    public function remaining(User $user): int
    {
        $sum = (int) MeetingQuotaTransaction::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [
                MeetingQuotaTransactionType::Consumed,
                MeetingQuotaTransactionType::Refunded,
                MeetingQuotaTransactionType::Purchased,
                MeetingQuotaTransactionType::AdminGrant,
            ])
            ->sum('amount');

        return $user->max_meetings + $sum;
    }

    /**
     * 受講生の面談履歴を新しい順で paginate する。
     * type フィルタを掛けた場合は該当 type のみ返す。
     *
     * @param ?MeetingQuotaTransactionType $type フィルタ type、null で全件
     *
     * @return LengthAwarePaginator<MeetingQuotaTransaction>
     */
    public function history(
        User $user,
        ?MeetingQuotaTransactionType $type = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        // 面談予約 Feature(Meeting Model) 未実装環境では relatedMeeting の Eager Loading は省略する。
        // Meeting Model 導入後は relatedMeeting.enrollment.certification まで一括ロードされる。
        $with = ['relatedPayment.meetingPack', 'grantedBy'];
        if (class_exists('App\\Models\\Meeting')) {
            $with[] = 'relatedMeeting.enrollment.certification';
        }

        return MeetingQuotaTransaction::query()
            ->where('user_id', $user->id)
            ->with($with)
            ->when($type, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }
}
