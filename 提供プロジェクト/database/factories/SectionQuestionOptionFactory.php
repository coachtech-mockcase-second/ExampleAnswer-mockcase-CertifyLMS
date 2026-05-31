<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SectionQuestion;
use App\Models\SectionQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionQuestionOption>
 */
class SectionQuestionOptionFactory extends Factory
{
    protected $model = SectionQuestionOption::class;

    public function definition(): array
    {
        return [
            'section_question_id' => SectionQuestion::factory(),
            'body' => fake()->sentence(4),
            'is_correct' => false,
            'order' => fake()->numberBetween(0, 5),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }

    public function wrong(): static
    {
        return $this->state(fn () => ['is_correct' => false]);
    }
}
