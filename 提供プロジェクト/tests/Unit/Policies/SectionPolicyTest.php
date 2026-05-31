<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Policies\SectionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SectionPolicy の判定を検証する Unit テスト。
 * admin: 全可 / coach: 担当のみ / student: section + chapter + part すべて Published 必要。
 */
class SectionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $section = Section::factory()->published()->create();
        $policy = new SectionPolicy;

        $this->assertTrue($policy->view($admin, $section));
        $this->assertTrue($policy->update($admin, $section));
        $this->assertTrue($policy->preview($admin, $section));
    }

    public function test_student_view_requires_full_published_chain(): void
    {
        $student = User::factory()->student()->create();
        $publishedPart = Part::factory()->published()->create();
        $publishedChapter = Chapter::factory()->for($publishedPart)->published()->create();
        $publishedSection = Section::factory()->for($publishedChapter)->published()->create();

        $draftPart = Part::factory()->draft()->create();
        $draftChapter = Chapter::factory()->for($draftPart)->published()->create();
        $sectionUnderDraftPart = Section::factory()->for($draftChapter)->published()->create();

        $policy = new SectionPolicy;

        $this->assertTrue($policy->view($student, $publishedSection), 'すべて Published なら閲覧可');
        $this->assertFalse($policy->view($student, $sectionUnderDraftPart), '親階層に draft があれば閲覧不可');
    }

    public function test_coach_can_manage_assigned_certification(): void
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
        $part = Part::factory()->for($assignedCert)->published()->create();
        $chapter = Chapter::factory()->for($part)->published()->create();
        $section = Section::factory()->for($chapter)->published()->create();
        $policy = new SectionPolicy;

        $this->assertTrue($policy->update($coach, $section));
        $this->assertTrue($policy->preview($coach, $section));
    }
}
