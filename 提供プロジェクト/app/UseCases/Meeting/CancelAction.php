<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Google\GoogleCalendarService;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use App\UseCases\Notification\NotifyMeetingCanceledAction;
use Illuminate\Support\Facades\DB;

/**
 * 当事者(受講生 or コーチ)による面談予約キャンセルのユースケース。
 *
 * - `reserved` 以外の状態(`canceled` / `completed`)は MeetingStatusTransitionException で拒否
 * - `scheduled_at <= now()` の Meeting は MeetingAlreadyStartedException で拒否(開始後はキャンセル不可)
 * - Meeting を `canceled` に遷移し、canceled_by_user_id / canceled_at を記録
 * - 面談回数を `refunded (+1)` で返却(消費トランザクションとは別レコードを INSERT)
 * - 相手方(受講生がキャンセルしたらコーチ、コーチがキャンセルしたら受講生)に通知
 *
 * Action 入口で `lockForUpdate()` を掛け、同時刻 race condition で 2 度キャンセルが走るのを防ぐ。
 *
 * @see \App\Http\Controllers\MeetingController::cancel()
 */
final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundAction,
        private readonly NotifyMeetingCanceledAction $notifyCanceled,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * @param User $actor キャンセル操作を実行した当事者(受講生 or コーチ)
     *
     * @throws MeetingStatusTransitionException
     * @throws MeetingAlreadyStartedException
     */
    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);

            ($this->refundAction)($locked->student, $locked->id);

            DB::afterCommit(function () use ($locked, $actor): void {
                // GCal に event がある場合は削除する。連携解除済 / event 未作成のケースは何もしない。
                $eventId = $locked->google_event_id;
                if ($eventId !== null) {
                    $credential = $locked->coach?->googleCredential;
                    if ($credential !== null) {
                        $this->googleCalendarService->deleteEvent($credential, $eventId);
                    }
                }
                ($this->notifyCanceled)($locked, $actor);
            });

            return $locked->fresh();
        });
    }
}
