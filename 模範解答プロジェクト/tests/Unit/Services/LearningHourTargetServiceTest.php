<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\LearningHourTargetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningHourTargetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_returns_unset_target_with_studied_hours(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->closed(3600)
            ->create();

        $summary = app(LearningHourTargetService::class)->compute($enrollment);

        $this->assertNull($summary->targetTotalHours);
        $this->assertSame(3600, $summary->studiedTotalSeconds);
        $this->assertEqualsWithDelta(1.0, $summary->studiedTotalHours, 0.01);
        $this->assertNull($summary->progressRatio);
    }

    public function test_compute_returns_remaining_hours_when_target_set(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningHourTarget::factory()->forEnrollment($enrollment)->hours(10)->create();
        LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->closed(3600 * 4)
            ->create();

        $summary = app(LearningHourTargetService::class)->compute($enrollment);

        $this->assertSame(10, $summary->targetTotalHours);
        $this->assertEqualsWithDelta(4.0, $summary->studiedTotalHours, 0.01);
        $this->assertEqualsWithDelta(6.0, $summary->remainingHours, 0.01);
        $this->assertEqualsWithDelta(0.4, $summary->progressRatio, 0.01);
    }

    public function test_compute_clamps_progress_ratio_to_one(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningHourTarget::factory()->forEnrollment($enrollment)->hours(1)->create();
        LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->closed(3600 * 5)
            ->create();

        $summary = app(LearningHourTargetService::class)->compute($enrollment);

        $this->assertEqualsWithDelta(1.0, $summary->progressRatio, 0.01);
        $this->assertSame(0.0, $summary->remainingHours);
    }
}
