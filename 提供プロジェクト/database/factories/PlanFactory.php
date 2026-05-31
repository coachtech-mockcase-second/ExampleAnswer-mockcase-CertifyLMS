<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $duration = fake()->randomElement([30, 90, 180, 365]);
        $quota = fake()->numberBetween(2, 24);

        return [
            'name' => fake()->randomElement([
                '1 ヶ月プラン',
                '3 ヶ月プラン',
                '6 ヶ月プラン',
                '12 ヶ月プラン',
                'ビギナープラン',
                'スタンダードプラン',
                'アドバンストプラン',
            ]).' '.$quota.' 回',
            'description' => fake()->paragraph(),
            'duration_days' => $duration,
            'default_meeting_quota' => $quota,
            'status' => PlanStatus::Draft->value,
            'sort_order' => fake()->numberBetween(0, 100),
            'created_by_user_id' => User::factory()->admin(),
            'updated_by_user_id' => function (array $attributes) {
                return $attributes['created_by_user_id'];
            },
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Draft->value]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Published->value]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Archived->value]);
    }

    public function withDurationDays(int $days): static
    {
        return $this->state(fn () => ['duration_days' => $days]);
    }
}
