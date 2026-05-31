<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Enums\MeetingReminderWindow;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Notifications\Mentoring\MeetingReminderNotification;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 予約済の面談を当事者 (受講生 + コーチ両方) にリマインドするラッパー Action。
 *
 * `SendMeetingRemindersCommand` から呼ばれ、window (Eve / OneHourBefore) ごとに対象 Meeting を巡回。
 * 同一 `(meeting_id, window)` の組合せで既に通知が存在する場合は配信をスキップして冪等性を確保する
 * (Schedule の重複起動 / 手動再実行に耐える)。
 */
final class NotifyMeetingReminderAction
{
    public function __invoke(Meeting $meeting, MeetingReminderWindow $window): void
    {
        if ($this->alreadyDispatched($meeting, $window)) {
            return;
        }

        $meeting->loadMissing(['student', 'coach']);

        foreach ([$meeting->student, $meeting->coach] as $user) {
            if ($user === null) {
                continue;
            }
            if ($user->status !== UserStatus::InProgress) {
                continue;
            }

            $user->notify(new MeetingReminderNotification($meeting, $window));
        }
    }

    private function alreadyDispatched(Meeting $meeting, MeetingReminderWindow $window): bool
    {
        return DatabaseNotification::query()
            ->where('type', MeetingReminderNotification::class)
            ->where('data->meeting_id', $meeting->id)
            ->where('data->window', $window->value)
            ->exists();
    }
}
