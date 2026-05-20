<?php

declare(strict_types=1);

namespace Tests\Feature\Commands\Notification;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_window_targets_meetings_scheduled_tomorrow(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $tomorrowMeeting = Meeting::factory()->reserved()
            ->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addDay()->setTime(10, 0)]);

        // 当日 / 翌々日の Meeting は対象外
        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->setTime(10, 0)]);
        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addDays(2)->setTime(10, 0)]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertSuccessful();

        Notification::assertSentTimes(MeetingReminderNotification::class, 2);
        Notification::assertSentTo($student, MeetingReminderNotification::class);
        Notification::assertSentTo($coach, MeetingReminderNotification::class);
    }

    public function test_one_hour_before_window_targets_meetings_in_next_55_to_65_minutes(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addMinutes(60)]);

        // 範囲外
        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addMinutes(30)]);
        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addMinutes(120)]);

        $this->artisan('notifications:send-meeting-reminders --window=one_hour_before')->assertSuccessful();

        Notification::assertSentTimes(MeetingReminderNotification::class, 2);
    }

    public function test_duplicate_invocation_does_not_resend(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addDay()->setTime(10, 0)]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertSuccessful();
        $this->assertSame(2, \DB::table('notifications')
            ->where('type', MeetingReminderNotification::class)->count());

        Notification::fake();
        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_invalid_window_returns_invalid_exit_code(): void
    {
        $this->artisan('notifications:send-meeting-reminders --window=bogus')
            ->assertExitCode(2);
    }

    public function test_canceled_and_completed_meetings_are_excluded(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        // 翌日に canceled / completed の Meeting を作る
        Meeting::factory()->canceled()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addDay()->setTime(10, 0)]);
        Meeting::factory()->completed()->forStudent($student)->forCoach($coach)
            ->create(['scheduled_at' => now()->addDay()->setTime(11, 0)]);

        $this->artisan('notifications:send-meeting-reminders --window=eve')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
