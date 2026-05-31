<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\CertificationCategory;
use App\Models\User;
use App\Policies\CertificationCategoryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationCategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_perform_all_category_operations(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();
        $policy = new CertificationCategoryPolicy;

        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $category));
        $this->assertTrue($policy->delete($admin, $category));
    }

    public function test_coach_and_student_cannot_manage_categories(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $category = CertificationCategory::factory()->create();
        $policy = new CertificationCategoryPolicy;

        foreach ([$coach, $student] as $user) {
            $this->assertFalse($policy->viewAny($user));
            $this->assertFalse($policy->create($user));
            $this->assertFalse($policy->update($user, $category));
            $this->assertFalse($policy->delete($user, $category));
        }
    }
}
