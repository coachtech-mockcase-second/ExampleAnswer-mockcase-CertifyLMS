<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談予約キャンセル時の面談回数返却ユースケース。
 *
 * `consumed` トランザクションと相殺する `refunded(+1)` を INSERT する。
 * 面談キャンセル Action からは同一 DB トランザクション内で呼ばれる前提。
 */
final class RefundQuotaAction
{
    /**
     * @param  string  $meetingId  キャンセルされた面談(Meeting)の ULID
     */
    public function __invoke(User $user, string $meetingId): MeetingQuotaTransaction
    {
        return DB::transaction(fn () => MeetingQuotaTransaction::create([
            'user_id' => $user->id,
            'type' => MeetingQuotaTransactionType::Refunded,
            'amount' => 1,
            'related_meeting_id' => $meetingId,
            'occurred_at' => now(),
        ]));
    }
}
