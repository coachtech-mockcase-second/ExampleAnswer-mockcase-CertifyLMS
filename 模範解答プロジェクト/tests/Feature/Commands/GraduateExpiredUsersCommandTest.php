<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\UserPlanLogEventType;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraduateExpiredUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_users_past_plan_expiration_are_graduated(): void
    {
        $plan = Plan::factory()->published()->create();
        $expired1 = User::factory()->inProgress()->withPlan($plan)->create(['plan_expires_at' => now()->subDay()]);
        $expired2 = User::factory()->inProgress()->withPlan($plan)->create(['plan_expires_at' => now()->subHour()]);
        $stillActive = User::factory()->inProgress()->withPlan($plan)->create(['plan_expires_at' => now()->addDay()]);

        $this->artisan('users:graduate-expired')
            ->assertExitCode(0)
            ->expectsOutputToContain('Graduated 2 expired users.');

        $this->assertSame(UserStatus::Graduated, $expired1->fresh()->status);
        $this->assertSame(UserStatus::Graduated, $expired2->fresh()->status);
        $this->assertSame(UserStatus::InProgress, $stillActive->fresh()->status);
    }

    public function test_invited_and_graduated_users_are_not_affected(): void
    {
        $plan = Plan::factory()->published()->create();
        $invited = User::factory()->invited()->create([
            'plan_id' => $plan->id,
            'plan_expires_at' => now()->subDay(),
        ]);
        $alreadyGraduated = User::factory()->graduated()->withPlan($plan)->create(['plan_expires_at' => now()->subDay()]);

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        $this->assertSame(UserStatus::Invited, $invited->fresh()->status);
        $this->assertSame(UserStatus::Graduated, $alreadyGraduated->fresh()->status);
    }

    public function test_status_log_and_plan_log_are_recorded(): void
    {
        $plan = Plan::factory()->published()->create();
        $user = User::factory()->inProgress()->withPlan($plan)->create(['plan_expires_at' => now()->subDay()]);

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $user->id,
            'status' => 'graduated',
            'changed_by_user_id' => null,
            'changed_reason' => '期限満了による自動卒業',
        ]);

        $this->assertDatabaseHas('user_plan_logs', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'event_type' => UserPlanLogEventType::Expired->value,
            'changed_by_user_id' => null,
            'changed_reason' => '期限満了',
        ]);
    }

    public function test_users_with_null_plan_expires_at_are_skipped(): void
    {
        $user = User::factory()->inProgress()->create([
            'plan_id' => null,
            'plan_expires_at' => null,
        ]);

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        $this->assertSame(UserStatus::InProgress, $user->fresh()->status);
    }
}
