<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\Mentoring\MeetingReservedNotification;

/**
 * 受講生の予約成立を担当コーチに配信するラッパー Action。
 *
 * 面談予約 Feature の `Meeting\StoreAction` (または `ReserveAction`) から `DB::afterCommit` 内で呼ばれる。
 * 受講生宛は予約 UI で即時確認できるため発火しない。コーチが `withdrawn / graduated` の場合は配信スキップ。
 */
final class NotifyMeetingReservedAction
{
    public function __invoke(Meeting $meeting): void
    {
        $coach = $meeting->loadMissing('coach')->coach;

        if ($coach === null) {
            return;
        }
        if ($coach->status !== UserStatus::InProgress) {
            return;
        }

        $coach->notify(new MeetingReservedNotification($meeting));
    }
}
