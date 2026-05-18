<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\CoachGoogleCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingCanceledNotification;
use App\Services\Google\GoogleCalendarService;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cancels_reserved_meeting_and_refunds_quota(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = app(CancelAction::class)($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertSame($student->id, $result->canceled_by_user_id);
        $this->assertNotNull($result->canceled_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_coach_cancels_and_notifies_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        app(CancelAction::class)($meeting, $coach);

        Notification::assertSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_throws_when_meeting_already_started(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinutes(5)->startOfHour(),
        ]);

        $this->expectException(MeetingAlreadyStartedException::class);
        try {
            app(CancelAction::class)($meeting, $student);
        } finally {
            $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
        }
    }

    public function test_throws_when_meeting_not_reserved(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $completed = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();

        $this->expectException(MeetingStatusTransitionException::class);
        app(CancelAction::class)($completed, $student);
    }

    public function test_deletes_gcal_event_when_meeting_has_event_id_and_coach_is_connected(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        CoachGoogleCredential::factory()->forCoach($coach)->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_event_id' => 'gcal-event-zzz',
        ]);

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteEvent')->once()->withArgs(
                fn ($credential, $eventId) => $eventId === 'gcal-event-zzz',
            );
        });

        app(CancelAction::class)($meeting, $student);
    }

    public function test_skips_gcal_delete_when_meeting_has_no_event_id(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        CoachGoogleCredential::factory()->forCoach($coach)->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_event_id' => null,
        ]);

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('deleteEvent');
        });

        app(CancelAction::class)($meeting, $student);
    }
}
