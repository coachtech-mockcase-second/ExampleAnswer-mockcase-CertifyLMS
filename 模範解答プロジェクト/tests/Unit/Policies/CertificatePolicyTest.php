<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($admin, $certificate));
    }

    public function test_owner_student_can_download(): void
    {
        $student = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($student)->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($student, $certificate));
    }

    public function test_other_student_cannot_download(): void
    {
        $owner = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($owner)->create();
        $policy = new CertificatePolicy;

        $this->assertFalse($policy->download($stranger, $certificate));
    }

    public function test_coach_can_download_only_for_assigned_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $assignedCert = Certificate::factory()->create(['certification_id' => $certification->id]);
        $otherCert = Certificate::factory()->create();

        $assignedCert->load('certification.coaches');
        $otherCert->load('certification.coaches');

        $policy = new CertificatePolicy;

        $this->assertTrue($policy->download($coach, $assignedCert));
        $this->assertFalse($policy->download($coach, $otherCert));
    }
}
