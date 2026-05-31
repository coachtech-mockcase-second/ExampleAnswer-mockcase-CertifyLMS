<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;
use App\Policies\SectionQuestionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SectionQuestionPolicy の判定を検証する Unit テスト。
 * admin / coach 担当 / student 受講中 + Published のロール分岐を網羅する。
 */
class SectionQuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $section = $question->section;
        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->viewAny($admin, $section));
        $this->assertTrue($policy->view($admin, $question));
        $this->assertTrue($policy->update($admin, $question));
    }

    public function test_student_with_enrollment_can_view_published_question(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->published()->create();
        $cert = $question->section->chapter->part->certification;
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->view($student, $question));
    }

    public function test_student_without_enrollment_cannot_view(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->published()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertFalse($policy->view($student, $question), '未受講の student は view 不可');
    }

    public function test_student_cannot_view_draft_question(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->draft()->create();
        $cert = $question->section->chapter->part->certification;
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertFalse($policy->view($student, $question), 'draft の question は受講中でも閲覧不可');
    }

    public function test_coach_assigned_only(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedQuestion = SectionQuestion::factory()->published()->create([
            'section_id' => Section::factory()->state(fn () => [
                'chapter_id' => \App\Models\Chapter::factory()
                    ->for(\App\Models\Part::factory()->for($assignedCert))
                    ->create()->id,
            ]),
        ]);
        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->update($coach, $assignedQuestion));
    }
}
