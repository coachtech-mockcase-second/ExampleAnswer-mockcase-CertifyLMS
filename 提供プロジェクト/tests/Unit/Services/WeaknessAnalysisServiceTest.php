<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PassProbabilityBand;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\Models\QuestionCategory;
use App\Services\WeaknessAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeaknessAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private WeaknessAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WeaknessAnalysisService::class);
    }

    public function test_get_weak_categories_returns_empty_when_no_graded_session(): void
    {
        $enrollment = Enrollment::factory()->create();

        $weak = $this->service->getWeakCategories($enrollment);

        $this->assertTrue($weak->isEmpty());
    }

    public function test_get_weak_categories_returns_categories_below_70_percent_threshold(): void
    {
        // 合格点 60 → 閾値 60 * 0.70 = 42 → 正答率 42% 未満が「弱点」判定
        $cert = Certification::factory()->published()->create();
        $catGood = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => '強い分野'])->create();
        $catWeak = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => '弱い分野'])->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->passingScore(60)->create();

        $session = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(true, 5, 6)
            ->create(['passing_score_snapshot' => 60]);

        // 強い分野: 3 問中 3 正解(100%) / 弱い分野: 3 問中 1 正解(33%、閾値 42 未満)
        $this->seedAnswers($session, $catGood, 3, correctCount: 3);
        $this->seedAnswers($session, $catWeak, 3, correctCount: 1);

        $weak = $this->service->getWeakCategories($enrollment);

        $this->assertCount(1, $weak);
        $this->assertSame($catWeak->id, $weak->first()->id);
    }

    public function test_get_heatmap_returns_cell_per_category(): void
    {
        $cert = Certification::factory()->published()->create();
        $catA = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => 'A'])->create();
        $catB = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => 'B'])->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();
        $session = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(true)
            ->create();

        $this->seedAnswers($session, $catA, 4, correctCount: 4);
        $this->seedAnswers($session, $catB, 2, correctCount: 1);

        $heatmap = $this->service->getHeatmap($session);

        $this->assertCount(2, $heatmap);

        $cellA = $heatmap->firstWhere('categoryId', $catA->id);
        $this->assertSame(4, $cellA->totalCount);
        $this->assertSame(4, $cellA->correctCount);
        $this->assertSame(100.0, $cellA->correctRate);

        $cellB = $heatmap->firstWhere('categoryId', $catB->id);
        $this->assertSame(2, $cellB->totalCount);
        $this->assertSame(1, $cellB->correctCount);
        $this->assertSame(50.0, $cellB->correctRate);
    }

    public function test_get_pass_probability_band_returns_unknown_when_no_graded(): void
    {
        $enrollment = Enrollment::factory()->create();
        $band = $this->service->getPassProbabilityBand($enrollment);
        $this->assertSame(PassProbabilityBand::Unknown, $band);
    }

    public function test_get_pass_probability_band_returns_safe_when_above_90_percent_threshold(): void
    {
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->passingScore(60)->create();

        // 平均 90 点(合格点 60 の 150%)→ Safe
        MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(true, 9, 10)
            ->create(['passing_score_snapshot' => 60]);

        $band = $this->service->getPassProbabilityBand($enrollment);
        $this->assertSame(PassProbabilityBand::Safe, $band);
    }

    public function test_get_pass_probability_band_returns_danger_when_below_70_percent_threshold(): void
    {
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->passingScore(60)->create();

        // 平均 30 点(合格点 60 の 50%、閾値 42 未満)→ Danger
        MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(false, 3, 10)
            ->create(['passing_score_snapshot' => 60]);

        $band = $this->service->getPassProbabilityBand($enrollment);
        $this->assertSame(PassProbabilityBand::Danger, $band);
    }

    public function test_batch_heatmap_returns_aggregated_array_per_session(): void
    {
        $cert = Certification::factory()->published()->create();
        $catA = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => 'A'])->create();
        $catB = QuestionCategory::factory()->state(['certification_id' => $cert->id, 'name' => 'B'])->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();

        $session1 = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(true)
            ->create();
        $this->seedAnswers($session1, $catA, 4, correctCount: 4);
        $this->seedAnswers($session1, $catB, 2, correctCount: 1);

        $session2 = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(false)
            ->create();
        $this->seedAnswers($session2, $catA, 4, correctCount: 0);

        $heatmap = $this->service->batchHeatmap(collect([$session1, $session2]));

        $this->assertArrayHasKey($session1->id, $heatmap);
        $this->assertArrayHasKey($session2->id, $heatmap);

        $cellsA1 = collect($heatmap[$session1->id])->firstWhere('category_id', $catA->id);
        $this->assertSame(4, $cellsA1['correct']);
        $this->assertSame(4, $cellsA1['total']);
        $this->assertSame(100.0, $cellsA1['rate']);
        $this->assertSame('A', $cellsA1['category_name']);

        $cellsA2 = collect($heatmap[$session2->id])->firstWhere('category_id', $catA->id);
        $this->assertSame(0, $cellsA2['correct']);
        $this->assertSame(4, $cellsA2['total']);
        $this->assertSame(0.0, $cellsA2['rate']);
    }

    public function test_batch_heatmap_excludes_non_graded_sessions(): void
    {
        $cert = Certification::factory()->published()->create();
        $cat = QuestionCategory::factory()->state(['certification_id' => $cert->id])->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->create();

        $graded = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(true)
            ->create();
        $this->seedAnswers($graded, $cat, 2, correctCount: 1);

        $notStarted = MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->notStarted()
            ->create();

        $heatmap = $this->service->batchHeatmap(collect([$graded, $notStarted]));

        $this->assertArrayHasKey($graded->id, $heatmap);
        $this->assertArrayNotHasKey($notStarted->id, $heatmap);
    }

    public function test_batch_heatmap_returns_empty_for_empty_collection(): void
    {
        $this->assertSame([], $this->service->batchHeatmap(collect()));
    }

    public function test_get_pass_probability_band_uses_only_recent_three_sessions(): void
    {
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert)->learning()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->published()->passingScore(60)->create();

        // 4 件のうち直近 3 件が高得点なら Safe(古い 1 件の低得点は無視)
        MockExamSession::factory()
            ->forEnrollment($enrollment)
            ->forMockExam($mockExam)
            ->graded(false, 2, 10)
            ->create([
                'passing_score_snapshot' => 60,
                'graded_at' => now()->subDays(30),
            ]);
        for ($i = 0; $i < 3; $i++) {
            MockExamSession::factory()
                ->forEnrollment($enrollment)
                ->forMockExam($mockExam)
                ->graded(true, 9, 10)
                ->create([
                    'passing_score_snapshot' => 60,
                    'graded_at' => now()->subDays($i),
                ]);
        }

        $band = $this->service->getPassProbabilityBand($enrollment);
        $this->assertSame(PassProbabilityBand::Safe, $band);
    }

    /**
     * 指定 category で answers + question を投入するヘルパ。
     */
    private function seedAnswers(MockExamSession $session, QuestionCategory $category, int $count, int $correctCount): void
    {
        for ($i = 0; $i < $count; $i++) {
            $question = MockExamQuestion::factory()
                ->forMockExam($session->mockExam)
                ->forCategory($category)
                ->withOptions()
                ->create();

            MockExamAnswer::factory()->create([
                'mock_exam_session_id' => $session->id,
                'mock_exam_question_id' => $question->id,
                'selected_option_id' => $question->options->first()->id,
                'selected_option_body' => $question->options->first()->body,
                'is_correct' => $i < $correctCount,
                'answered_at' => now(),
            ]);
        }
    }
}
