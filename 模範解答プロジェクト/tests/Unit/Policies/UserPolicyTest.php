<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_all_user_management_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();
        $policy = new UserPolicy();

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->view($admin, $target));
        $this->assertTrue($policy->update($admin, $target));
        $this->assertTrue($policy->updateRole($admin, $target));
        $this->assertTrue($policy->withdraw($admin, $target));
    }

    public function test_coach_cannot_perform_user_management_operations(): void
    {
        $coach = User::factory()->coach()->create();
        $target = User::factory()->student()->create();
        $policy = new UserPolicy();

        $this->assertFalse($policy->viewAny($coach));
        $this->assertFalse($policy->view($coach, $target));
        $this->assertFalse($policy->update($coach, $target));
        $this->assertFalse($policy->updateRole($coach, $target));
        $this->assertFalse($policy->withdraw($coach, $target));
    }

    public function test_student_cannot_perform_user_management_operations(): void
    {
        $student = User::factory()->student()->create();
        $target = User::factory()->student()->create();
        $policy = new UserPolicy();

        $this->assertFalse($policy->viewAny($student));
        $this->assertFalse($policy->view($student, $target));
        $this->assertFalse($policy->update($student, $target));
        $this->assertFalse($policy->updateRole($student, $target));
        $this->assertFalse($policy->withdraw($student, $target));
    }
}
