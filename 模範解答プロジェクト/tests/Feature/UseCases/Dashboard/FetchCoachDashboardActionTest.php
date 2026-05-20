<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use App\UseCases\Dashboard\FetchCoachDashboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class FetchCoachDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_enrollments_come_from_certification_coaches_pivot(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $myCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $this->attachCoach($myCert, $coach);

        $mine = Enrollment::factory()->for($myCert)->learning()->create();
        Enrollment::factory()->for($otherCert)->learning()->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertCount(1, $vm->assignedEnrollments);
        $this->assertSame($mine->id, $vm->assignedEnrollments->first()->id);
    }

    public function test_assigned_enrollments_carry_last_activity_at_via_with_max(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();

        LearningSession::factory()
            ->forEnrollment($enrollment)
            ->forUser($enrollment->user)
            ->closed()
            ->startedOn(now()->subDay())
            ->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $first = $vm->assignedEnrollments->first();
        $this->assertNotNull($first->last_activity_at);
    }

    public function test_only_passed_and_learning_enrollments_are_displayed(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        Enrollment::factory()->for($cert)->learning()->create();
        Enrollment::factory()->for($cert)->passed()->create(['passed_at' => now()]);
        Enrollment::factory()->for($cert)->failed()->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $statuses = $vm->assignedEnrollments->map(fn (Enrollment $e) => $e->status)->all();
        $this->assertNotContains(EnrollmentStatus::Failed, $statuses);
    }

    public function test_today_and_tomorrow_meetings_are_scoped_to_coach(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($cert)->for($student)->learning()->create();

        Meeting::factory()->state([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved,
            'scheduled_at' => now()->addHours(2),
        ])->create();
        Meeting::factory()->state([
            'coach_id' => $coach->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => MeetingStatus::Reserved,
            'scheduled_at' => now()->addDays(2),
        ])->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertCount(1, $vm->todayAndTomorrowMeetings);
    }

    public function test_unanswered_qa_count_only_includes_threads_without_replies(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $this->attachCoach($cert, $coach);
        $student = User::factory()->student()->inProgress()->create();
        $openThread = QaThread::factory()->for($cert)->for($student)->state(['status' => QaThreadStatus::Open])->create();
        $answeredThread = QaThread::factory()->for($cert)->for($student)->state(['status' => QaThreadStatus::Open])->create();
        QaReply::factory()->for($answeredThread, 'thread')->for($coach, 'user')->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertSame(1, $vm->unansweredQaCount);
        $this->assertSame($openThread->id, $vm->recentQaThreads->first()->id);
    }

    public function test_unread_chat_count_uses_service(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $mock = Mockery::mock(ChatUnreadCountService::class);
        $mock->shouldReceive('roomCountForUser')->with(Mockery::on(fn (User $u) => $u->id === $coach->id))->andReturn(7);
        $this->app->instance(ChatUnreadCountService::class, $mock);

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertSame(7, $vm->unreadChatCount);
    }

    public function test_view_model_does_not_carry_v3_dropped_properties(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertFalse(property_exists($vm, 'aggregatedWeakCategories'));
        $this->assertFalse(property_exists($vm, 'recentEnrollmentNotes'));
        $this->assertFalse(property_exists($vm, 'stagnationList'));
    }

    public function test_safe_returns_null_when_unread_chat_service_throws(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $mock = Mockery::mock(ChatUnreadCountService::class);
        $mock->shouldReceive('roomCountForUser')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(ChatUnreadCountService::class, $mock);

        $vm = app(FetchCoachDashboardAction::class)($coach);

        $this->assertNull($vm->unreadChatCount);
    }

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
