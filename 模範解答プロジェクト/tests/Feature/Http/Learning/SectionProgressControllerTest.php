<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Section 読了マーク (markRead / unmarkRead) の HTTP 統合テスト。
 * cascade visibility / Enrollment 状態 / 冪等性 / 認可分岐を検証する。
 */
class SectionProgressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_read_creates_section_progress(): void
    {
        [$student, $section] = $this->buildSection();

        $response = $this->actingAs($student)
            ->post(route('learning.sections.markRead', $section));

        $response->assertRedirect(route('learning.sections.show', $section));
        $response->assertSessionHas('section_just_completed', $section->id);
        $enrollment = $student->enrollments()->first();
        $this->assertDatabaseHas('section_progresses', [
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'deleted_at' => null,
        ]);
    }

    public function test_mark_read_allowed_for_passed_enrollment(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Passed);

        $response = $this->actingAs($student)
            ->postJson(route('learning.sections.markRead', $section));

        $this->assertContains($response->status(), [200, 302]);
        $enrollment = $student->enrollments()->first();
        $this->assertDatabaseHas('section_progresses', [
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_mark_read_409_for_failed_enrollment(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Failed);

        $response = $this->actingAs($student)
            ->postJson(route('learning.sections.markRead', $section));

        $this->assertSame(409, $response->status());
    }

    public function test_mark_read_409_for_draft_section(): void
    {
        [$student, $section] = $this->buildSection(EnrollmentStatus::Learning, sectionStatus: ContentStatus::Draft);

        $response = $this->actingAs($student)
            ->postJson(route('learning.sections.markRead', $section));

        $this->assertSame(409, $response->status());
    }

    public function test_unmark_read_soft_deletes_progress(): void
    {
        [$student, $section] = $this->buildSection();
        $enrollment = $student->enrollments()->first();
        $progress = SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section)
            ->create();

        $this->actingAs($student)
            ->delete(route('learning.sections.unmarkRead', $section))
            ->assertRedirect();

        $this->assertSoftDeleted('section_progresses', ['id' => $progress->id]);
    }

    public function test_mark_read_restores_soft_deleted_progress(): void
    {
        [$student, $section] = $this->buildSection();
        $enrollment = $student->enrollments()->first();
        $progress = SectionProgress::factory()
            ->forEnrollment($enrollment)
            ->forSection($section)
            ->create();
        $progress->delete();

        $this->actingAs($student)
            ->post(route('learning.sections.markRead', $section))
            ->assertRedirect();

        $this->assertDatabaseHas('section_progresses', [
            'id' => $progress->id,
            'deleted_at' => null,
        ]);
    }

    public function test_mark_read_forbidden_for_non_enrolled_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $section = $this->buildLooseSection();

        $response = $this->actingAs($student)
            ->postJson(route('learning.sections.markRead', $section));

        $this->assertSame(403, $response->status());
    }

    /**
     * @return array{0: User, 1: Section}
     */
    private function buildSection(
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Learning,
        ContentStatus $sectionStatus = ContentStatus::Published,
    ): array {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)
            ->state(['status' => $enrollmentStatus->value])->create();

        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create(['status' => $sectionStatus->value]);

        return [$student, $section];
    }

    private function buildLooseSection(): Section
    {
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);

        return Section::factory()->for($chapter)->create(['status' => ContentStatus::Published->value]);
    }
}
