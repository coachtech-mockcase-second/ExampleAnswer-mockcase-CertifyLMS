<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Http\Controllers\MeetingController;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use App\Services\Google\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use App\UseCases\Notification\NotifyMeetingReservedAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生の面談予約を成立させるユースケース。
 *
 * - 残面談回数 1 件以上を要求(0 件なら InsufficientMeetingQuotaException)
 * - 指定時刻が担当コーチ集合の有効な面談可能時間枠内であることを検証(枠外なら MeetingOutOfAvailabilityException)
 * - 担当コーチ集合 ∩ 当該時刻に空き枠あり ∩ 当該時刻に他予約なし のコーチ集合を抽出
 * - 過去 30 日の completed 数が最少のコーチを 1 名選出(同数 ULID 昇順、決定論的)
 * - meeting_url_snapshot に選出コーチの固定面談 URL を焼き込み、`reserved` で INSERT
 * - 同時刻 race condition は (coach_id, scheduled_at) UNIQUE 違反として検知し MeetingNoAvailableCoachException に変換
 * - 面談回数を `consumed (-1)` で消費し meeting_quota_transaction_id を Meeting に紐づけ
 * - 担当コーチ宛のみ通知(受講生宛は予約 UI で即時確認のため不要)
 *
 * 全ステップを単一 DB トランザクション内で実行し、通知は `DB::afterCommit` で commit 後に発火する。
 *
 * @see MeetingController::store()
 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeAction,
        private readonly NotifyMeetingReservedAction $notifyReserved,
        private readonly GoogleCalendarService $googleCalendarService,
    ) {}

    /**
     * @throws InsufficientMeetingQuotaException
     * @throws MeetingOutOfAvailabilityException
     * @throws MeetingNoAvailableCoachException
     */
    public function __invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting
    {
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $topic) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // 同時刻に他受講生が先行予約した race condition: UNIQUE(coach_id, scheduled_at) で弾かれた
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeAction)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);

            // GCal 連携済コーチなら event を作成して event_id を焼き込む。GCal は付加機能のため失敗しても予約は成立扱い。
            DB::afterCommit(function () use ($meeting, $coach): void {
                $credential = $coach->googleCredential;
                if ($credential !== null) {
                    $eventId = $this->googleCalendarService->insertEvent($credential, $meeting);
                    if ($eventId !== null) {
                        $meeting->update(['google_event_id' => $eventId]);
                    }
                }
                ($this->notifyReserved)($meeting);
            });

            return $meeting->fresh();
        });
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、
     * (2) 当該時刻に reserved / completed の Meeting を持たないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');

        return $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->get();
    }
}
