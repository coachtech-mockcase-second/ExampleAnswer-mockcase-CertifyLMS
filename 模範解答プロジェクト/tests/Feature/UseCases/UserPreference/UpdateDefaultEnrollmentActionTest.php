<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\UserPreference;

use App\Exceptions\UserPreference\DefaultEnrollmentInvalidTargetException;
use App\Models\Enrollment;
use App\Models\User;
use App\UseCases\UserPreference\UpdateDefaultEnrollmentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDefaultEnrollmentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_default_enrollment_for_learning_target(): void
    {
        $user = User::factory()->student()->create();
        $target = Enrollment::factory()->for($user)->learning()->create();

        $result = app(UpdateDefaultEnrollmentAction::class)($user, $target);

        $this->assertSame($target->id, $result->default_enrollment_id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_enrollment_id' => $target->id,
        ]);
    }

    public function test_updates_default_enrollment_for_passed_target(): void
    {
        $user = User::factory()->student()->create();
        $target = Enrollment::factory()->for($user)->passed()->create();

        $result = app(UpdateDefaultEnrollmentAction::class)($user, $target);

        $this->assertSame($target->id, $result->default_enrollment_id);
    }

    public function test_overwrites_existing_default(): void
    {
        $user = User::factory()->student()->create();
        $previous = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $previous->id]);
        $next = Enrollment::factory()->for($user)->learning()->create();

        app(UpdateDefaultEnrollmentAction::class)($user, $next);

        $this->assertSame($next->id, $user->fresh()->default_enrollment_id);
    }

    public function test_throws_when_target_is_failed(): void
    {
        $user = User::factory()->student()->create();
        $target = Enrollment::factory()->for($user)->failed()->create();

        $this->expectException(DefaultEnrollmentInvalidTargetException::class);

        app(UpdateDefaultEnrollmentAction::class)($user, $target);
    }

    public function test_failed_target_does_not_modify_existing_default(): void
    {
        $user = User::factory()->student()->create();
        $current = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $current->id]);
        $failedTarget = Enrollment::factory()->for($user)->failed()->create();

        try {
            app(UpdateDefaultEnrollmentAction::class)($user, $failedTarget);
        } catch (DefaultEnrollmentInvalidTargetException) {
            // expected
        }

        $this->assertSame($current->id, $user->fresh()->default_enrollment_id);
    }
}
