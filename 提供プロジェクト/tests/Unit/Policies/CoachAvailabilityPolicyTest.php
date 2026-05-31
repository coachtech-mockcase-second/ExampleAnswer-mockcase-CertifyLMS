<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\CoachAvailability;
use App\Models\User;
use App\Policies\CoachAvailabilityPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CoachAvailabilityPolicy の判定を検証する Unit テスト。
 * viewAny / view は全ロール許可（受講生も予約画面で他コーチ枠を閲覧）、
 * create / update / delete は coach 本人 (coach_id === user.id) のみ。
 */
class CoachAvailabilityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_returns_true_for_all_roles(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new CoachAvailabilityPolicy;

        // Act & Assert
        $this->assertTrue($policy->viewAny($admin));
        $this->assertTrue($policy->viewAny($coach));
        $this->assertTrue($policy->viewAny($student), '受講生も予約画面で全コーチ枠を閲覧するため viewAny=true');
    }

    public function test_create_is_allowed_only_for_coach(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new CoachAvailabilityPolicy;

        // Act & Assert
        $this->assertTrue($policy->create($coach));
        $this->assertFalse($policy->create($admin));
        $this->assertFalse($policy->create($student));
    }

    public function test_update_is_allowed_only_for_owner_coach(): void
    {
        // Arrange
        $owner = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($owner)->active()->create();
        $policy = new CoachAvailabilityPolicy;

        // Act & Assert
        $this->assertTrue($policy->update($owner, $availability), '自分の枠は更新可');
        $this->assertFalse($policy->update($otherCoach, $availability), '他コーチの枠は更新不可');
    }

    public function test_delete_inherits_update_authorization(): void
    {
        // Arrange
        $owner = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $availability = CoachAvailability::factory()->forCoach($owner)->active()->create();
        $policy = new CoachAvailabilityPolicy;

        // Act & Assert
        $this->assertTrue($policy->delete($owner, $availability));
        $this->assertFalse($policy->delete($otherCoach, $availability));
    }
}
