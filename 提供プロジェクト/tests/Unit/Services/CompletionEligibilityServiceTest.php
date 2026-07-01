<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Services\CompletionEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompletionEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CompletionEligibilityService::class);
    }

    public function test_returns_false_when_no_published_mock_exam_exists(): void
    {
        $enrollment = Enrollment::factory()->create();

        $this->assertFalse($this->service->isEligible($enrollment));
    }

    public function test_returns_false_when_only_unpublished_mock_exams_exist(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        MockExam::factory()->for($certification)->create(['is_published' => false]);

        $this->assertFalse($this->service->isEligible($enrollment));
    }

    public function test_returns_false_when_not_all_published_mock_exams_passed(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();

        $exam1 = MockExam::factory()->for($certification)->create(['is_published' => true]);
        $exam2 = MockExam::factory()->for($certification)->create(['is_published' => true]);

        MockExamSession::factory()->for($enrollment)->for($exam1)->create(['pass' => true]);
        // exam2 は未合格

        $this->assertFalse($this->service->isEligible($enrollment));
    }

    public function test_returns_true_when_all_published_mock_exams_have_passing_session(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();

        $exam1 = MockExam::factory()->for($certification)->create(['is_published' => true]);
        $exam2 = MockExam::factory()->for($certification)->create(['is_published' => true]);

        MockExamSession::factory()->for($enrollment)->for($exam1)->create(['pass' => true]);
        MockExamSession::factory()->for($enrollment)->for($exam2)->create(['pass' => true]);

        $this->assertTrue($this->service->isEligible($enrollment));
    }

    public function test_returns_true_when_same_exam_has_multiple_passing_sessions(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->create();
        $exam = MockExam::factory()->for($certification)->create(['is_published' => true]);

        // 同じ exam に対し 2 セッションともに合格(DISTINCT mock_exam_id でカウントされ 1 件として扱われる)
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['pass' => true]);
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['pass' => true]);

        $this->assertTrue($this->service->isEligible($enrollment));
    }
}
