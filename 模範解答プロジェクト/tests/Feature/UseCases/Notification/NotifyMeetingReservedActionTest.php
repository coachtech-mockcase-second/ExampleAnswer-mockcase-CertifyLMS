<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\UseCases\Notification\NotifyMeetingReservedAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyMeetingReservedActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_only_to_coach_not_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingReservedAction::class)($meeting);

        Notification::assertSentTo($coach, MeetingReservedNotification::class);
        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
    }

    public function test_skips_when_coach_is_withdrawn(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->withdrawn()->create();
        $meeting = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();

        app(NotifyMeetingReservedAction::class)($meeting);

        Notification::assertNothingSent();
    }
}
