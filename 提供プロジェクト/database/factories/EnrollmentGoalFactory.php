<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->randomElement([
                '過去問 5 年分を解き終える',
                '苦手分野の正答率を 80% 以上にする',
                '模試で合格点を 1 度クリアする',
                '参考書 1 冊を 30 日で読破する',
            ]),
            'description' => fake()->boolean(60) ? fake()->realText(120) : null,
            'target_date' => fake()->boolean(70) ? now()->addDays(fake()->numberBetween(7, 90))->toDateString() : null,
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => [
            'achieved_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
