<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingCanceledNotification;

/**
 * 当事者によるキャンセルを相手方に通知するラッパー Action。
 *
 * `Meeting\CancelAction` から `DB::afterCommit` 内で呼ばれる。
 * actor が受講生ならコーチ宛、actor がコーチなら受講生宛に通知を送る。
 * 受信者が `withdrawn` の場合は送信スキップする(受講生は graduated でも通知対象に含めて履歴を残す)。
 *
 * @see \App\UseCases\Meeting\CancelAction
 */
final class NotifyMeetingCanceledAction
{
    public function __invoke(Meeting $meeting, User $actor): void
    {
        $meeting->loadMissing(['student', 'coach']);

        $recipient = $actor->role === UserRole::Coach
            ? $meeting->student
            : $meeting->coach;

        if ($recipient === null || $recipient->status === UserStatus::Withdrawn) {
            return;
        }

        $recipient->notify(new MeetingCanceledNotification($meeting, $actor));
    }
}
