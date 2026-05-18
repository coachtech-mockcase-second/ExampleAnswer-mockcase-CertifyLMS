<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Section;
use App\Models\User;
use App\Services\SessionCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_close_open_sessions_marks_auto_closed(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $session = LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->open()
            ->create();

        app(SessionCloseService::class)->closeOpenSessions($student, asAutoClosed: true);

        $session->refresh();
        $this->assertNotNull($session->ended_at);
        $this->assertTrue($session->auto_closed);
        $this->assertGreaterThan(0, $session->duration_seconds);
    }

    public function test_close_one_clamps_duration_to_max_session_seconds(): void
    {
        config()->set('learning.max_session_seconds', 60);
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $session = LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->state(['started_at' => now()->subHours(2), 'ended_at' => null, 'duration_seconds' => null])
            ->create();

        app(SessionCloseService::class)->closeOne($session, asAutoClosed: true);

        $session->refresh();
        $this->assertSame(60, $session->duration_seconds);
    }

    public function test_close_stale_sessions_closes_expired_sessions(): void
    {
        config()->set('learning.max_session_seconds', 60);
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        $stale = LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->state(['started_at' => now()->subHours(2), 'ended_at' => null])
            ->create();
        $fresh = LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->state(['started_at' => now()->subSeconds(10), 'ended_at' => null])
            ->create();

        $count = app(SessionCloseService::class)->closeStaleSessions();

        $this->assertSame(1, $count);
        $stale->refresh();
        $fresh->refresh();
        $this->assertNotNull($stale->ended_at);
        $this->assertTrue($stale->auto_closed);
        $this->assertNull($fresh->ended_at);
    }
}
