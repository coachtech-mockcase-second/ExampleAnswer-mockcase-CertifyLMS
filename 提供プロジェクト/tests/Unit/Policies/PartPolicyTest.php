<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Part;
use App\Models\User;
use App\Policies\PartPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PartPolicy の判定を検証する Unit テスト。
 * admin / coach 担当 / student published のロール分岐を網羅する。
 */
class PartPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->for($cert)->published()->create();
        $policy = new PartPolicy;

        $this->assertTrue($policy->viewAny($admin, $cert));
        $this->assertTrue($policy->view($admin, $part));
        $this->assertTrue($policy->update($admin, $part));
        $this->assertTrue($policy->delete($admin, $part));
    }

    public function test_coach_assigned_only(): void
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
        $assignedPart = Part::factory()->for($assignedCert)->published()->create();
        $otherPart = Part::factory()->for($otherCert)->published()->create();
        $policy = new PartPolicy;

        $this->assertTrue($policy->update($coach, $assignedPart));
        $this->assertFalse($policy->update($coach, $otherPart));
    }

    public function test_student_view_published_only(): void
    {
        $student = User::factory()->student()->create();
        $publishedPart = Part::factory()->published()->create();
        $draftPart = Part::factory()->draft()->create();
        $policy = new PartPolicy;

        $this->assertTrue($policy->view($student, $publishedPart));
        $this->assertFalse($policy->view($student, $draftPart));
        $this->assertFalse($policy->update($student, $publishedPart));
    }
}
