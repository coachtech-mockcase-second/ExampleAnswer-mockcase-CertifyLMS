<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->view($admin, $certificate));
        $this->assertTrue($policy->download($admin, $certificate));
    }

    public function test_owner_can_view_and_download_own_certificate(): void
    {
        $student = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($student)->create();
        $policy = new CertificatePolicy;

        $this->assertTrue($policy->view($student, $certificate));
        $this->assertTrue($policy->download($student, $certificate));
    }

    public function test_other_student_cannot_view_or_download(): void
    {
        $owner = User::factory()->student()->create();
        $stranger = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($owner)->create();
        $policy = new CertificatePolicy;

        $this->assertFalse($policy->view($stranger, $certificate));
        $this->assertFalse($policy->download($stranger, $certificate));
    }
}
