<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\CertificationCoachAssignmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationCoachAssignmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_delete_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $policy = new CertificationCoachAssignmentPolicy();

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->delete($admin));
    }

    public function test_coach_and_student_cannot_manage_assignments(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new CertificationCoachAssignmentPolicy();

        foreach ([$coach, $student] as $user) {
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->delete($user));
        }
    }
}
