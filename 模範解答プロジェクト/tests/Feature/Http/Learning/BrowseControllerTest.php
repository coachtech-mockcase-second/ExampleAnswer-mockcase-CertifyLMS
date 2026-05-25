<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Learning;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 受講生向け教材ブラウジング Controller の HTTP 統合テスト。
 * 認可分岐 / EnsureActiveLearning / Section auto-start の代表シナリオを網羅する。
 */
class BrowseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_when_default_enrollment_set(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $student->update(['default_enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($student)->get(route('learning.index'));

        $response->assertRedirect(route('learning.enrollments.show', $enrollment));
    }

    public function test_index_shows_empty_state_when_no_default_and_multiple_enrollments(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->count(2)->for($student)->learning()->create();

        $response = $this->actingAs($student)->get(route('learning.index'));

        $response->assertOk();
        $response->assertViewIs('learning.index');
    }

    public function test_index_403_for_graduated_user(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($student)->get(route('learning.index'));

        $response->assertForbidden();
    }

    public function test_show_enrollment_for_passed_status_is_visible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();

        $response = $this->actingAs($student)->get(route('learning.enrollments.show', $enrollment));

        $response->assertOk();
        $response->assertViewIs('learning.enrollments.show');
    }

    public function test_show_enrollment_forbidden_for_other_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = Enrollment::factory()->learning()->create();

        $response = $this->actingAs($student)->get(route('learning.enrollments.show', $other));

        $response->assertForbidden();
    }

    public function test_show_part_allows_passed_enrollment(): void
    {
        [$student, $certification] = $this->buildStudentAndCertification(EnrollmentStatus::Passed);
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);

        $response = $this->actingAs($student)->get(route('learning.parts.show', $part));

        $response->assertOk();
    }

    public function test_show_part_forbidden_for_non_enrolled_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $part = Part::factory()->create(['status' => ContentStatus::Published->value]);

        $response = $this->actingAs($student)->get(route('learning.parts.show', $part));

        $response->assertForbidden();
    }

    public function test_show_chapter_404_when_draft_part(): void
    {
        [$student, $certification] = $this->buildStudentAndCertification();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Draft->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);

        $response = $this->actingAs($student)->get(route('learning.chapters.show', $chapter));

        $response->assertNotFound();
    }

    public function test_show_section_auto_starts_learning_session(): void
    {
        [$student, $certification, $section] = $this->buildSectionFor(EnrollmentStatus::Learning);

        $response = $this->actingAs($student)->get(route('learning.sections.show', $section));

        $response->assertOk();
        $this->assertDatabaseHas('learning_sessions', [
            'user_id' => $student->id,
            'section_id' => $section->id,
            'ended_at' => null,
        ]);
    }

    public function test_show_section_auto_closes_previous_open_session(): void
    {
        [$student, $certification, $section] = $this->buildSectionFor(EnrollmentStatus::Learning);
        $enrollment = $student->enrollments()->first();

        $openSession = LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->open()
            ->create();

        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertOk();

        $this->assertDatabaseHas('learning_sessions', [
            'id' => $openSession->id,
            'auto_closed' => true,
        ]);
        $this->assertDatabaseMissing('learning_sessions', [
            'id' => $openSession->id,
            'ended_at' => null,
        ]);
    }

    public function test_show_section_for_passed_enrollment_succeeds(): void
    {
        [$student, $certification, $section] = $this->buildSectionFor(EnrollmentStatus::Passed);

        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertOk();
    }

    public function test_show_section_for_failed_enrollment_returns_403(): void
    {
        [$student, $certification, $section] = $this->buildSectionFor(EnrollmentStatus::Failed);

        $this->actingAs($student)->get(route('learning.sections.show', $section))->assertForbidden();
    }

    public function test_show_part_404_when_certification_archived(): void
    {
        [$student, $part] = $this->buildArchivedCertificationPart();

        $this->actingAs($student)
            ->get(route('learning.parts.show', $part))
            ->assertNotFound();
    }

    public function test_show_chapter_404_when_certification_archived(): void
    {
        [$student, $part] = $this->buildArchivedCertificationPart();
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);

        $this->actingAs($student)
            ->get(route('learning.chapters.show', $chapter))
            ->assertNotFound();
    }

    public function test_show_section_404_when_certification_archived(): void
    {
        [$student, $part] = $this->buildArchivedCertificationPart();
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create([
            'status' => ContentStatus::Published->value,
            'body' => '# テスト本文',
        ]);

        $this->actingAs($student)
            ->get(route('learning.sections.show', $section))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Certification}
     */
    private function buildStudentAndCertification(EnrollmentStatus $status = EnrollmentStatus::Learning): array
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($certification)->state(['status' => $status->value])->create();

        return [$student, $certification];
    }

    /**
     * @return array{0: User, 1: Certification, 2: Section}
     */
    private function buildSectionFor(EnrollmentStatus $status): array
    {
        [$student, $certification] = $this->buildStudentAndCertification($status);
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);
        $chapter = Chapter::factory()->for($part)->create(['status' => ContentStatus::Published->value]);
        $section = Section::factory()->for($chapter)->create([
            'status' => ContentStatus::Published->value,
            'body' => '# テスト本文',
        ]);

        return [$student, $certification, $section];
    }

    /**
     * 受講登録(learning)済みだが資格が公開停止(archived)のシナリオ。配下 Part は Published。
     *
     * @return array{0: User, 1: Part}
     */
    private function buildArchivedCertificationPart(): array
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->archived()->create();
        Enrollment::factory()->for($student)->for($certification)->learning()->create();
        $part = Part::factory()->for($certification)->create(['status' => ContentStatus::Published->value]);

        return [$student, $part];
    }
}
