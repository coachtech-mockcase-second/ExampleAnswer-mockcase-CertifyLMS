<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use App\Policies\CertificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_all_certification_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();
        $policy = new CertificationPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $cert));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $cert));
        $this->assertTrue($policy->delete($admin, $cert));
        $this->assertTrue($policy->publish($admin, $cert));
        $this->assertTrue($policy->unpublish($admin, $cert));
        $this->assertTrue($policy->archive($admin, $cert));
        $this->assertTrue($policy->attachCoach($admin, $cert));
        $this->assertTrue($policy->detachCoach($admin, $cert));
    }

    public function test_admin_can_view_any_status_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new CertificationPolicy;

        $this->assertTrue($policy->view($admin, Certification::factory()->draft()->create()));
        $this->assertTrue($policy->view($admin, Certification::factory()->archived()->create()));
        $this->assertTrue($policy->view($admin, Certification::factory()->published()->create()));
    }

    public function test_coach_can_view_only_assigned_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();

        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $assignedCert->load('coaches');
        $otherCert->load('coaches');

        $policy = new CertificationPolicy;

        $this->assertTrue($policy->view($coach, $assignedCert));
        $this->assertFalse($policy->view($coach, $otherCert));
        // viewAny は admin/coach 共通許可。表示行は Certification::scopeForUser で絞り込む設計。
        $this->assertTrue($policy->viewAny($coach));
        $this->assertFalse($policy->create($coach));
        $this->assertFalse($policy->update($coach, $assignedCert));
        $this->assertFalse($policy->attachCoach($coach, $assignedCert));
    }

    public function test_student_can_view_only_published_and_non_deleted(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();
        $archived = Certification::factory()->archived()->create();
        $policy = new CertificationPolicy;

        $this->assertTrue($policy->view($student, $published));
        $this->assertFalse($policy->view($student, $draft));
        $this->assertFalse($policy->view($student, $archived));
        $this->assertFalse($policy->viewAny($student));
        $this->assertFalse($policy->create($student));
        $this->assertFalse($policy->update($student, $published));
    }
}
