<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_zero_for_certification_without_sections(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_counts_only_published_sections(): void
    {
        $enrollment = $this->buildEnrollmentWithSections(publishedSections: 3, draftSections: 2);

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(3, $summary->sectionsTotal);
    }

    public function test_summarize_completion_ratio_reflects_completed_sections(): void
    {
        $enrollment = $this->buildEnrollmentWithSections(publishedSections: 4);
        $sections = Section::query()->take(2)->get();
        foreach ($sections as $section) {
            SectionProgress::factory()
                ->forEnrollment($enrollment)
                ->forSection($section)
                ->create();
        }

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertEqualsWithDelta(0.5, $summary->overallCompletionRatio, 0.001);
    }

    public function test_batch_calculate_returns_keyed_ratios(): void
    {
        $enrollment1 = $this->buildEnrollmentWithSections(publishedSections: 2);
        $enrollment2 = $this->buildEnrollmentWithSections(publishedSections: 0);

        $result = app(ProgressService::class)->batchCalculate(collect([$enrollment1, $enrollment2]));

        $this->assertArrayHasKey($enrollment1->id, $result);
        $this->assertArrayHasKey($enrollment2->id, $result);
    }

    private function buildEnrollmentWithSections(int $publishedSections, int $draftSections = 0): Enrollment
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();

        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        for ($i = 0; $i < $publishedSections; $i++) {
            Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
        }
        for ($i = 0; $i < $draftSections; $i++) {
            Section::factory()->for($chapter)->create(['status' => ContentStatus::Draft->value]);
        }

        return $enrollment;
    }
}
