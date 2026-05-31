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

    /**
     * 平日(月〜金、Carbon dayOfWeek の 1〜5)からランダム選択。
     */
    public function weekday(): static
    {
        return $this->state(fn () => [
            'day_of_week' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * 週末(土日、Carbon dayOfWeek の 0 と 6)からランダム選択。
     */
    public function weekend(): static
    {
        return $this->state(fn () => [
            'day_of_week' => fake()->randomElement([0, 6]),
        ]);
    }

    /**
     * 午前枠(09:00 〜 12:00)。
     */
    public function morning(): static
    {
        return $this->timeRange('09:00:00', '12:00:00');
    }

    /**
     * 夕方〜夜の枠(18:00 〜 21:00)。
     */
    public function evening(): static
    {
        return $this->timeRange('18:00:00', '21:00:00');
    }
}
