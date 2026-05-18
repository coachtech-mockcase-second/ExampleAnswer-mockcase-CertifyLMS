<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\Mentoring\MeetingReservedNotification;

/**
 * 受講生の予約成立を担当コーチに通知するラッパー Action。
 *
 * 面談予約 Feature の `Meeting\StoreAction` から `DB::afterCommit` 内で呼ばれる。
 * 受信者(コーチ)が `withdrawn` / `graduated` の場合は送信スキップする(運用上はコーチに graduated 状態は無いが、防衛的に判定)。
 *
 * @see \App\UseCases\Meeting\StoreAction
 */ 
final class NotifyMeetingReservedAction
{
    public function __invoke(Meeting $meeting): void
    {
        $coach = $meeting->loadMissing('coach')->coach;

        if ($coach === null || $coach->status !== UserStatus::InProgress) {
            return;
        }

        $coach->notify(new MeetingReservedNotification($meeting));
    }
}
