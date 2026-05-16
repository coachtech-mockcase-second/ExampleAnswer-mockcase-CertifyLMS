<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\User;
use App\Policies\CertificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertTrue($policy->archive($admin, $cert));
        $this->assertTrue($policy->unarchive($admin, $cert));
    }

    public function test_admin_can_view_draft_and_archived_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new CertificationPolicy;

        $this->assertTrue($policy->view($admin, Certification::factory()->draft()->create()));
        $this->assertTrue($policy->view($admin, Certification::factory()->archived()->create()));
        $this->assertTrue($policy->view($admin, Certification::factory()->published()->create()));
    }

    public function test_coach_and_student_can_only_view_published_certifications(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $draft = Certification::factory()->draft()->create();
        $published = Certification::factory()->published()->create();
        $archived = Certification::factory()->archived()->create();
        $policy = new CertificationPolicy;

        $this->assertFalse($policy->view($coach, $draft));
        $this->assertFalse($policy->view($coach, $archived));
        $this->assertTrue($policy->view($coach, $published));

        $this->assertFalse($policy->view($student, $draft));
        $this->assertFalse($policy->view($student, $archived));
        $this->assertTrue($policy->view($student, $published));
    }

    public function test_coach_and_student_cannot_perform_admin_operations(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $cert = Certification::factory()->draft()->create();
        $policy = new CertificationPolicy;

        $this->assertFalse($policy->viewAny($coach));
        $this->assertFalse($policy->create($coach));
        $this->assertFalse($policy->update($coach, $cert));
        $this->assertFalse($policy->delete($coach, $cert));
        $this->assertFalse($policy->publish($coach, $cert));

        $this->assertFalse($policy->viewAny($student));
        $this->assertFalse($policy->create($student));
        $this->assertFalse($policy->update($student, $cert));
        $this->assertFalse($policy->delete($student, $cert));
    }
}
