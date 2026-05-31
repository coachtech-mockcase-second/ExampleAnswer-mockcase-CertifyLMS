<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionImage;
use App\Models\User;
use App\Policies\SectionImagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SectionImagePolicy の create / delete を検証する Unit テスト。
 * admin: 全可 / coach: 担当資格のみ / student: 全不可。
 */
class SectionImagePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $section = Section::factory()->published()->create();
        $image = SectionImage::factory()->for($section)->create();
        $policy = new SectionImagePolicy;

        $this->assertTrue($policy->create($admin, $section));
        $this->assertTrue($policy->delete($admin, $image));
    }

    public function test_coach_only_for_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedSection = Section::factory()->for(
            Chapter::factory()->for(Part::factory()->for($assignedCert)->published())->published()
        )->published()->create();
        $otherSection = Section::factory()->for(
            Chapter::factory()->for(Part::factory()->for($otherCert)->published())->published()
        )->published()->create();
        $policy = new SectionImagePolicy;

        $this->assertTrue($policy->create($coach, $assignedSection));
        $this->assertFalse($policy->create($coach, $otherSection));
    }

    public function test_student_cannot(): void
    {
        $student = User::factory()->student()->create();
        $section = Section::factory()->published()->create();
        $image = SectionImage::factory()->for($section)->create();
        $policy = new SectionImagePolicy;

        $this->assertFalse($policy->create($student, $section));
        $this->assertFalse($policy->delete($student, $image));
    }
}
