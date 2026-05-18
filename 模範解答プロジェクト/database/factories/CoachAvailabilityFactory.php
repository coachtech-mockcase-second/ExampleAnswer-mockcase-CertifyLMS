<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachAvailability>
 */
class CoachAvailabilityFactory extends Factory
{
    protected $model = CoachAvailability::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coach_id' => User::factory()->coach(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ];
    }

    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'coach_id' => $coach->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function onDay(int $dayOfWeek): static
    {
        return $this->state(fn () => ['day_of_week' => $dayOfWeek]);
    }

    public function monday(): static
    {
        return $this->onDay(1);
    }

    public function tuesday(): static
    {
        return $this->onDay(2);
    }

    public function wednesday(): static
    {
        return $this->onDay(3);
    }

    public function thursday(): static
    {
        return $this->onDay(4);
    }

    public function friday(): static
    {
        return $this->onDay(5);
    }

    public function saturday(): static
    {
        return $this->onDay(6);
    }

    public function sunday(): static
    {
        return $this->onDay(0);
    }

    public function timeRange(string $start, string $end): static
    {
        return $this->state(fn () => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
