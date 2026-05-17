<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingQuotaPlanStatus;
use App\Models\MeetingQuotaPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingQuotaPlan>
 */
class MeetingQuotaPlanFactory extends Factory
{
    protected $model = MeetingQuotaPlan::class;

    public function definition(): array
    {
        $count = fake()->randomElement([1, 3, 5, 10]);
        $price = $count * fake()->numberBetween(2500, 3500);

        return [
            'name' => $count.' 回パック',
            'description' => fake()->paragraph(),
            'meeting_count' => $count,
            'price' => $price,
            'stripe_price_id' => null,
            'status' => MeetingQuotaPlanStatus::Draft->value,
            'sort_order' => fake()->numberBetween(0, 100),
            'created_by_user_id' => User::factory()->admin(),
            'updated_by_user_id' => function (array $attributes) {
                return $attributes['created_by_user_id'];
            },
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => MeetingQuotaPlanStatus::Draft->value]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => MeetingQuotaPlanStatus::Published->value]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => MeetingQuotaPlanStatus::Archived->value]);
    }

    public function withCount(int $count): static
    {
        return $this->state(fn () => ['meeting_count' => $count]);
    }

    public function withPrice(int $price): static
    {
        return $this->state(fn () => ['price' => $price]);
    }
}
