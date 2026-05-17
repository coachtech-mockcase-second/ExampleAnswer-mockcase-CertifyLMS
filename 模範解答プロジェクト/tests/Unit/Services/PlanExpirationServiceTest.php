<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PlanExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanExpirationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_expired_returns_false_when_plan_expires_at_is_null(): void
    {
        $user = User::factory()->create(['plan_expires_at' => null]);

        $this->assertFalse(app(PlanExpirationService::class)->isExpired($user));
    }

    public function test_is_expired_returns_false_when_plan_expires_at_is_future(): void
    {
        $user = User::factory()->create(['plan_expires_at' => now()->addDays(10)]);

        $this->assertFalse(app(PlanExpirationService::class)->isExpired($user));
    }

    public function test_is_expired_returns_true_when_plan_expires_at_is_past(): void
    {
        $user = User::factory()->create(['plan_expires_at' => now()->subDay()]);

        $this->assertTrue(app(PlanExpirationService::class)->isExpired($user));
    }

    public function test_days_remaining_returns_minus_one_when_plan_expires_at_is_null(): void
    {
        $user = User::factory()->create(['plan_expires_at' => null]);

        $this->assertSame(-1, app(PlanExpirationService::class)->daysRemaining($user));
    }

    public function test_days_remaining_returns_positive_value_for_future(): void
    {
        $user = User::factory()->create(['plan_expires_at' => now()->addDays(15)]);

        $result = app(PlanExpirationService::class)->daysRemaining($user);

        $this->assertGreaterThan(0, $result);
        $this->assertLessThanOrEqual(15, $result);
    }

    public function test_days_remaining_returns_zero_when_expired(): void
    {
        $user = User::factory()->create(['plan_expires_at' => now()->subDays(5)]);

        $this->assertSame(0, app(PlanExpirationService::class)->daysRemaining($user));
    }
}
