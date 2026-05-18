<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\DefaultEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultEnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_after_create_sets_default_when_null(): void
    {
        $user = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($user)->learning()->create();

        app(DefaultEnrollmentService::class)->resolveAfterCreate($user, $enrollment);

        $this->assertSame($enrollment->id, $user->fresh()->default_enrollment_id);
    }

    public function test_resolve_after_create_keeps_existing_default(): void
    {
        $user = User::factory()->student()->create();
        $first = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $first->id]);

        $second = Enrollment::factory()->for($user)->learning()->create();

        app(DefaultEnrollmentService::class)->resolveAfterCreate($user, $second);

        $this->assertSame($first->id, $user->fresh()->default_enrollment_id);
    }

    public function test_resolve_after_status_change_transfers_to_only_remaining_active(): void
    {
        $user = User::factory()->student()->create();
        $defaulted = Enrollment::factory()->for($user)->learning()->create();
        $other = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);

        // defaulted を failed 扱いでサービスに通知
        $defaulted->update(['status' => 'failed']);

        app(DefaultEnrollmentService::class)->resolveAfterStatusChange($user, $defaulted);

        $this->assertSame($other->id, $user->fresh()->default_enrollment_id);
    }

    public function test_resolve_after_status_change_clears_to_null_when_two_or_more_remaining(): void
    {
        $user = User::factory()->student()->create();
        $defaulted = Enrollment::factory()->for($user)->learning()->create();
        Enrollment::factory()->for($user)->learning()->create();
        Enrollment::factory()->for($user)->passed()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);

        $defaulted->update(['status' => 'failed']);

        app(DefaultEnrollmentService::class)->resolveAfterStatusChange($user, $defaulted);

        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_resolve_after_status_change_clears_to_null_when_zero_remaining(): void
    {
        $user = User::factory()->student()->create();
        $defaulted = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);

        $defaulted->update(['status' => 'failed']);

        app(DefaultEnrollmentService::class)->resolveAfterStatusChange($user, $defaulted);

        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_resolve_after_status_change_does_nothing_when_not_default(): void
    {
        $user = User::factory()->student()->create();
        $defaulted = Enrollment::factory()->for($user)->learning()->create();
        $other = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $defaulted->id]);

        // default ではない other を failed にしてサービスに通知 → default はそのまま
        $other->update(['status' => 'failed']);

        app(DefaultEnrollmentService::class)->resolveAfterStatusChange($user, $other);

        $this->assertSame($defaulted->id, $user->fresh()->default_enrollment_id);
    }

    public function test_clear_if_invalid_clears_when_default_is_soft_deleted(): void
    {
        $user = User::factory()->student()->create();
        $default = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        $default->delete();

        app(DefaultEnrollmentService::class)->clearIfInvalid($user);

        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_clear_if_invalid_clears_when_default_is_failed(): void
    {
        $user = User::factory()->student()->create();
        $default = Enrollment::factory()->for($user)->failed()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        app(DefaultEnrollmentService::class)->clearIfInvalid($user);

        $this->assertNull($user->fresh()->default_enrollment_id);
    }

    public function test_clear_if_invalid_keeps_default_when_learning(): void
    {
        $user = User::factory()->student()->create();
        $default = Enrollment::factory()->for($user)->learning()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        app(DefaultEnrollmentService::class)->clearIfInvalid($user);

        $this->assertSame($default->id, $user->fresh()->default_enrollment_id);
    }

    public function test_clear_if_invalid_keeps_default_when_passed(): void
    {
        $user = User::factory()->student()->create();
        $default = Enrollment::factory()->for($user)->passed()->create();
        $user->update(['default_enrollment_id' => $default->id]);

        app(DefaultEnrollmentService::class)->clearIfInvalid($user);

        $this->assertSame($default->id, $user->fresh()->default_enrollment_id);
    }

    public function test_clear_if_invalid_does_nothing_when_default_is_null(): void
    {
        $user = User::factory()->student()->create();
        Enrollment::factory()->for($user)->learning()->create();

        app(DefaultEnrollmentService::class)->clearIfInvalid($user);

        $this->assertNull($user->fresh()->default_enrollment_id);
    }
}
