<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\TermType;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Services\TermJudgementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermJudgementServiceTest extends TestCase
{
    use RefreshDatabase;

    private TermJudgementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TermJudgementService::class);
    }

    public function test_returns_basic_learning_when_no_mock_exam_session_exists(): void
    {
        $enrollment = Enrollment::factory()->create(['current_term' => TermType::BasicLearning->value]);

        $term = $this->service->recalculate($enrollment);

        $this->assertSame(TermType::BasicLearning, $term);
    }

    public function test_returns_basic_learning_when_only_not_started_session_exists(): void
    {
        $enrollment = Enrollment::factory()->create();
        $exam = MockExam::factory()->for($enrollment->certification)->create();
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['status' => 'not_started']);

        $term = $this->service->recalculate($enrollment);

        $this->assertSame(TermType::BasicLearning, $term);
    }

    public function test_returns_mock_practice_when_in_progress_session_exists(): void
    {
        $enrollment = Enrollment::factory()->create(['current_term' => TermType::BasicLearning->value]);
        $exam = MockExam::factory()->for($enrollment->certification)->create();
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['status' => 'in_progress']);

        $term = $this->service->recalculate($enrollment);

        $this->assertSame(TermType::MockPractice, $term);
        $this->assertSame(TermType::MockPractice, $enrollment->refresh()->current_term);
    }

    public function test_returns_mock_practice_when_submitted_session_exists(): void
    {
        $enrollment = Enrollment::factory()->create();
        $exam = MockExam::factory()->for($enrollment->certification)->create();
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['status' => 'submitted']);

        $term = $this->service->recalculate($enrollment);

        $this->assertSame(TermType::MockPractice, $term);
    }

    public function test_returns_mock_practice_when_graded_session_exists(): void
    {
        $enrollment = Enrollment::factory()->create();
        $exam = MockExam::factory()->for($enrollment->certification)->create();
        MockExamSession::factory()->for($enrollment)->for($exam)->create(['status' => 'graded']);

        $term = $this->service->recalculate($enrollment);

        $this->assertSame(TermType::MockPractice, $term);
    }

    public function test_does_not_update_when_current_term_already_matches(): void
    {
        $enrollment = Enrollment::factory()->create(['current_term' => TermType::BasicLearning->value]);
        $originalUpdatedAt = $enrollment->updated_at;

        sleep(1);
        $this->service->recalculate($enrollment);

        $this->assertEquals($originalUpdatedAt->timestamp, $enrollment->refresh()->updated_at->timestamp);
    }
}
