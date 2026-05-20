<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\PassProbabilityBand;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MeetingPack;
use App\Models\Plan;
use App\Models\User;
use App\Services\CompletionEligibilityService;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\ProgressService;
use App\UseCases\Dashboard\FetchStudentDashboardAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchStudentDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_cards_for_both_learning_and_passed_enrollments(): void
    {
        $student = $this->makeStudentWithPlan();
        $cert1 = Certification::factory()->published()->create(['name' => 'A']);
        $cert2 = Certification::factory()->published()->create(['name' => 'B']);
        Enrollment::factory()->for($student)->for($cert1)->learning()->create();
        Enrollment::factory()->for($student)->for($cert2)->passed()->create(['passed_at' => now()]);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertCount(2, $vm->enrollmentCards);
        $statuses = $vm->enrollmentCards->map(fn ($card) => $card->status)->all();
        $this->assertContains(EnrollmentStatus::Learning, $statuses);
        $this->assertContains(EnrollmentStatus::Passed, $statuses);
    }

    public function test_plan_info_panel_contains_remaining_meetings_and_published_quota_plans(): void
    {
        $student = $this->makeStudentWithPlan(maxMeetings: 5);
        MeetingPack::factory()->published()->create();
        MeetingPack::factory()->draft()->create();

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertNotNull($vm->planInfo);
        $this->assertSame(5, $vm->planInfo->meetingsRemaining);
        $this->assertCount(1, $vm->planInfo->meetingPacks);
    }

    public function test_passed_enrollments_are_ordered_by_passed_at_desc(): void
    {
        $student = $this->makeStudentWithPlan();
        $cert1 = Certification::factory()->published()->create(['name' => 'A']);
        $cert2 = Certification::factory()->published()->create(['name' => 'B']);
        $cert3 = Certification::factory()->published()->create(['name' => 'C']);
        Enrollment::factory()->for($student)->for($cert1)->passed()->create(['passed_at' => now()->subDays(10)]);
        Enrollment::factory()->for($student)->for($cert2)->passed()->create(['passed_at' => now()->subDays(2)]);
        Enrollment::factory()->for($student)->for($cert3)->passed()->create(['passed_at' => now()->subDays(5)]);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $names = $vm->passedEnrollments->map(fn (Enrollment $e) => $e->certification->name)->all();
        $this->assertSame(['B', 'C', 'A'], $names);
    }

    public function test_passed_enrollment_card_only_includes_pdf_link_when_certificate_exists(): void
    {
        $student = $this->makeStudentWithPlan();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->passed()->create([
            'passed_at' => now()->subDay(),
        ]);
        Certificate::factory()->for($student)->for($enrollment)->for($cert)->create();

        $vm = app(FetchStudentDashboardAction::class)($student);

        $card = $vm->enrollmentCards->first();
        $this->assertTrue($card->isPassed);
        $this->assertNotNull($card->certificateDownloadUrl);
        $this->assertStringContainsString('/certificates/', $card->certificateDownloadUrl);
    }

    public function test_can_receive_certificate_is_only_true_when_eligible_and_status_is_learning(): void
    {
        $student = $this->makeStudentWithPlan();
        $certLearning = Certification::factory()->published()->create();
        $certPassed = Certification::factory()->published()->create();
        $eligibleLearning = Enrollment::factory()->for($student)->for($certLearning)->learning()->create();
        $passed = Enrollment::factory()->for($student)->for($certPassed)->passed()
            ->create(['passed_at' => now()]);

        $eligibility = Mockery::mock(CompletionEligibilityService::class);
        $eligibility->shouldReceive('isEligible')->with(Mockery::on(fn (Enrollment $e) => $e->id === $eligibleLearning->id))->andReturnTrue();
        $eligibility->shouldReceive('isEligible')->with(Mockery::on(fn (Enrollment $e) => $e->id === $passed->id))->andReturnTrue();
        $this->app->instance(CompletionEligibilityService::class, $eligibility);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $learningCard = $vm->enrollmentCards->firstWhere('enrollmentId', $eligibleLearning->id);
        $passedCard = $vm->enrollmentCards->firstWhere('enrollmentId', $passed->id);
        $this->assertTrue($learningCard->canReceiveCertificate);
        $this->assertFalse($passedCard->canReceiveCertificate);
    }

    public function test_batch_calculate_is_used_so_progress_query_does_not_grow_per_enrollment(): void
    {
        $student = $this->makeStudentWithPlan();
        for ($i = 0; $i < 5; $i++) {
            $cert = Certification::factory()->published()->create();
            Enrollment::factory()->for($student)->for($cert)->learning()->create();
        }

        $progressMock = Mockery::mock(ProgressService::class);
        $progressMock->shouldReceive('batchCalculate')->once()->andReturn([]);
        $this->app->instance(ProgressService::class, $progressMock);

        app(FetchStudentDashboardAction::class)($student);
    }

    public function test_safe_helper_returns_null_when_streak_service_throws(): void
    {
        $student = $this->makeStudentWithPlan();

        $weakness = Mockery::mock(WeaknessAnalysisServiceContract::class);
        $weakness->shouldReceive('getWeakCategories')->andReturn(collect());
        $weakness->shouldReceive('getPassProbabilityBand')->andReturn(PassProbabilityBand::Unknown);
        $this->app->instance(WeaknessAnalysisServiceContract::class, $weakness);

        $streakMock = Mockery::mock(\App\Services\StreakService::class);
        $streakMock->shouldReceive('calculate')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(\App\Services\StreakService::class, $streakMock);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertNull($vm->streak);
    }

    public function test_has_no_enrollment_is_true_when_no_active_enrollments(): void
    {
        $student = $this->makeStudentWithPlan();

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertTrue($vm->hasNoEnrollment);
    }

    private function makeStudentWithPlan(int $maxMeetings = 0): User
    {
        $plan = Plan::factory()->published()->create();

        return User::factory()
            ->student()
            ->inProgress()
            ->withPlan($plan)
            ->create(['max_meetings' => $maxMeetings]);
    }
}
