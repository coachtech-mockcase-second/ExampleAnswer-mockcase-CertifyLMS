<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\CoachGoogleCredential;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Mentoring\MeetingReservedNotification;
use App\Services\Google\GoogleCalendarService;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    private function setupBookableContext(
        ?Carbon $scheduledAt = null,
        int $maxMeetings = 5,
    ): array {
        $scheduledAt ??= Carbon::parse('2026-06-01 10:00:00'); // Monday 10:00
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => $maxMeetings]);
        $coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);

        CoachAvailability::factory()->forCoach($coach)
            ->onDay($scheduledAt->dayOfWeek)
            ->timeRange('09:00:00', '18:00:00')
            ->create();

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();

        $this->actingAs($student);

        return [
            'student' => $student,
            'coach' => $coach,
            'certification' => $certification,
            'enrollment' => $enrollment,
            'scheduledAt' => $scheduledAt,
        ];
    }

    public function test_reserves_meeting_with_auto_assigned_coach_and_consumes_quota(): void
    {
        Notification::fake();
        $ctx = $this->setupBookableContext();

        $meeting = app(StoreAction::class)(
            enrollment: $ctx['enrollment'],
            scheduledAt: $ctx['scheduledAt'],
            topic: 'アルゴリズム分野の相談',
        );

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($ctx['coach']->id, $meeting->coach_id);
        $this->assertSame($ctx['student']->id, $meeting->student_id);
        $this->assertSame($ctx['enrollment']->id, $meeting->enrollment_id);
        $this->assertSame('https://meet.example.com/coach-room', $meeting->meeting_url_snapshot);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $ctx['student']->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
        ]);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);

        Notification::assertSentTo($ctx['coach'], MeetingReservedNotification::class);
    }

    public function test_throws_insufficient_quota_when_remaining_is_zero(): void
    {
        $ctx = $this->setupBookableContext(maxMeetings: 0);

        $this->expectException(InsufficientMeetingQuotaException::class);
        try {
            app(StoreAction::class)(
                enrollment: $ctx['enrollment'],
                scheduledAt: $ctx['scheduledAt'],
                topic: '相談したい',
            );
        } finally {
            $this->assertDatabaseCount('meetings', 0);
        }
    }

    public function test_throws_out_of_availability_when_slot_is_outside_coach_schedule(): void
    {
        $ctx = $this->setupBookableContext();

        $this->expectException(MeetingOutOfAvailabilityException::class);
        try {
            app(StoreAction::class)(
                enrollment: $ctx['enrollment'],
                scheduledAt: Carbon::parse('2026-06-01 22:00:00'), // 22:00 は availability 範囲外
                topic: '相談したい',
            );
        } finally {
            $this->assertDatabaseCount('meetings', 0);
        }
    }

    public function test_throws_out_of_availability_when_all_coaches_busy_at_slot(): void
    {
        Notification::fake();
        $ctx = $this->setupBookableContext();

        // 同じ時刻に他の受講生が既に予約済 → 空きコーチ数 = 0 のため validateSlot で枠外と判定される
        $otherStudent = User::factory()->student()->create();
        Meeting::factory()->reserved()->forCoach($ctx['coach'])->forStudent($otherStudent)->create([
            'scheduled_at' => $ctx['scheduledAt'],
        ]);

        $this->expectException(MeetingOutOfAvailabilityException::class);
        try {
            app(StoreAction::class)(
                enrollment: $ctx['enrollment'],
                scheduledAt: $ctx['scheduledAt'],
                topic: '相談したい',
            );
        } finally {
            // 既存予約のみ、新規 INSERT されていない
            $this->assertSame(1, Meeting::count());
        }
    }

    public function test_selects_least_loaded_coach_when_multiple_candidates_exist(): void
    {
        Notification::fake();
        $scheduledAt = Carbon::parse('2026-06-01 10:00:00');
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $certification = Certification::factory()->published()->create();

        $busyCoach = User::factory()->coach()->inProgress()->create();
        $freeCoach = User::factory()->coach()->inProgress()->create();
        $this->attachCoach($certification, $busyCoach);
        $this->attachCoach($certification, $freeCoach);

        foreach ([$busyCoach, $freeCoach] as $coach) {
            CoachAvailability::factory()->forCoach($coach)
                ->onDay($scheduledAt->dayOfWeek)
                ->timeRange('09:00:00', '18:00:00')
                ->create();
        }

        // busyCoach は過去 30 日に completed 3 件 / freeCoach は 0 件
        foreach ([3, 7, 12] as $daysAgo) {
            Meeting::factory()->completed()->forCoach($busyCoach)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }

        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $this->actingAs($student);

        $meeting = app(StoreAction::class)($enrollment, $scheduledAt, '相談したい');

        $this->assertSame($freeCoach->id, $meeting->coach_id);
    }

    public function test_unique_constraint_race_condition_converts_to_no_available_coach(): void
    {
        Notification::fake();
        $ctx = $this->setupBookableContext();

        // 候補抽出時には空きありと判定されるが、INSERT 直前に他リクエストが先に INSERT したケースを模擬
        // ここでは事前に同コーチ × 同時刻で `canceled` 状態の Meeting を作成しておく (canceled は候補抽出では除外されないが、UNIQUE 制約は status を問わず効くため race を再現できる)
        $otherStudent = User::factory()->student()->create();
        Meeting::factory()->canceled()->forCoach($ctx['coach'])->forStudent($otherStudent)->create([
            'scheduled_at' => $ctx['scheduledAt'],
        ]);

        $this->expectException(MeetingNoAvailableCoachException::class);
        app(StoreAction::class)($ctx['enrollment'], $ctx['scheduledAt'], '相談したい');
    }

    public function test_inserts_gcal_event_and_stores_google_event_id_for_credentialed_coach(): void
    {
        Notification::fake();
        $ctx = $this->setupBookableContext();
        CoachGoogleCredential::factory()->forCoach($ctx['coach'])->create();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock) {
            $mock->shouldReceive('freebusy')->andReturn([]);
            $mock->shouldReceive('insertEvent')->once()->andReturn('gcal-event-abc');
        });

        $meeting = app(StoreAction::class)($ctx['enrollment'], $ctx['scheduledAt'], '相談したい');

        // afterCommit で更新されるため fresh() を呼んで DB の最新値で確認
        $this->assertSame('gcal-event-abc', $meeting->fresh()->google_event_id);
    }

    public function test_does_not_call_gcal_insert_for_uncredentialed_coach(): void
    {
        Notification::fake();
        $ctx = $this->setupBookableContext();

        $this->mock(GoogleCalendarService::class, function (MockInterface $mock) {
            $mock->shouldReceive('freebusy')->andReturn([]);
            $mock->shouldNotReceive('insertEvent');
        });

        $meeting = app(StoreAction::class)($ctx['enrollment'], $ctx['scheduledAt'], '相談したい');

        $this->assertNull($meeting->google_event_id);
    }
}
