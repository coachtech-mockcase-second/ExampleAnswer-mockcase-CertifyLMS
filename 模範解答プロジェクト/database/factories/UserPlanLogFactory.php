<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserPlanLogEventType;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPlanLog>
 */
class UserPlanLogFactory extends Factory
{
    protected $model = UserPlanLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory()->published(),
            'event_type' => UserPlanLogEventType::Assigned->value,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addDays(30),
            'meeting_quota_initial' => fake()->numberBetween(2, 24),
            'changed_by_user_id' => null,
            'changed_reason' => null,
            'occurred_at' => now(),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['event_type' => UserPlanLogEventType::Assigned->value]);
    }

    public function renewed(): static
    {
        return $this->state(fn () => ['event_type' => UserPlanLogEventType::Renewed->value]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => ['event_type' => UserPlanLogEventType::Canceled->value]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['event_type' => UserPlanLogEventType::Expired->value]);
    }
}
