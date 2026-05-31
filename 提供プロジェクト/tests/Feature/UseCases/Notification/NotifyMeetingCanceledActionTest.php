<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\UseCases\Notification\NotifyMeetingCanceledAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyMeetingCanceledActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cancels_notifies_coach(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingCanceledAction::class)($meeting, $student);

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_coach_cancels_notifies_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingCanceledAction::class)($meeting, $coach);

        Notification::assertSentTo($student, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_skips_when_recipient_is_withdrawn(): void
    {
        Notification::fake();
        $student = User::factory()->student()->withdrawn()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        // コーチがキャンセル → 退会済受講生宛は skip
        app(NotifyMeetingCanceledAction::class)($meeting, $coach);

        Notification::assertNothingSent();
    }
}
