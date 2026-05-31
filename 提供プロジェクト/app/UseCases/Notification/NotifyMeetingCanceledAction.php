<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingCanceledNotification;

/**
 * 当事者によるキャンセルを相手方に配信するラッパー Action。
 *
 * `Meeting\CancelAction` から `DB::afterCommit` 内で呼ばれる。
 * actor が受講生なら相手は担当コーチ、actor がコーチなら相手は受講生。
 * 相手の `status !== InProgress` の場合は配信スキップする。
 */
final class NotifyMeetingCanceledAction
{
    public function __invoke(Meeting $meeting, User $actor): void
    {
        $meeting->loadMissing(['student', 'coach']);

        $recipient = $actor->role === UserRole::Coach
            ? $meeting->student
            : $meeting->coach;

        if ($recipient === null) {
            return;
        }
        if ($recipient->status !== UserStatus::InProgress) {
            return;
        }

        $recipient->notify(new MeetingCanceledNotification($meeting, $actor));
    }
}
