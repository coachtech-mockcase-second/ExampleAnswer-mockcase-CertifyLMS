<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingReminderNotification;
use App\UseCases\Notification\NotifyMeetingReminderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 受講生 + コーチ両方への配信 + 同一 (meeting_id, window) の重複排除を検証する。
 */
class NotifyMeetingReminderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reminder_to_both_student_and_coach(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }

    public function test_skips_duplicate_window_dispatch(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        // 1 回目: 通常配信 (Notification::fake せず DB へ書き込む)
        app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);
        $this->assertSame(2, \DB::table('notifications')
            ->where('type', MeetingReminderNotification::class)
            ->count(), '初回 dispatch で受講生 + コーチ 2 件');

        // 2 回目: 重複なので skip
        Notification::fake();
        app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);
        Notification::assertNothingSent();
    }

    public function test_different_window_can_be_dispatched_after_eve(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        Notification::fake();
        app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::OneHourBefore);

        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }
}
