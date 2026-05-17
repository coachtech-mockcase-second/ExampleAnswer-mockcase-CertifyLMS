<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\MeetingQuotaService;
use Illuminate\Support\Facades\DB;

/**
 * 面談予約成立時の面談回数消費ユースケース。
 *
 * 残数が 1 未満なら InsufficientMeetingQuotaException(HTTP 409) を throw する。
 * `related_meeting_id` には予約された Meeting の ID を持たせ、キャンセル時の `RefundQuotaAction` から
 * 紐付け参照できるようにする。面談予約 Action からは同一 DB トランザクション内で呼ばれる前提だが、
 * 自身も `DB::transaction()` でラップして同一受講生に対する同時消費を直列化する(集計値ベースの
 * 残数チェックと INSERT の間に発生しうる二重消費を防ぐ)。
 */
final class ConsumeQuotaAction
{
    public function __construct(
        private readonly MeetingQuotaService $service,
    ) {}

    /**
     * @param  string  $meetingId  予約した面談(Meeting)の ULID
     *
     * @throws InsufficientMeetingQuotaException
     */
    public function __invoke(User $user, string $meetingId): MeetingQuotaTransaction
    {
        return DB::transaction(function () use ($user, $meetingId) {
            // 同一受講生の同時消費を直列化: User 行に排他ロックを掛けることで、
            // 残数集計の SELECT → INSERT の間に他リクエストが INSERT を完了させる TOCTOU を防ぐ
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->service->remaining($user) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            return MeetingQuotaTransaction::create([
                'user_id' => $user->id,
                'type' => MeetingQuotaTransactionType::Consumed,
                'amount' => -1,
                'related_meeting_id' => $meetingId,
                'occurred_at' => now(),
            ]);
        });
    }
}
