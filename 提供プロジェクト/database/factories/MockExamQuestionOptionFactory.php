<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MockExamQuestion;
use App\Models\MockExamQuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExamQuestionOption>
 */
class MockExamQuestionOptionFactory extends Factory
{
    protected $model = MockExamQuestionOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mock_exam_question_id' => MockExamQuestion::factory(),
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

    public function forQuestion(MockExamQuestion $question): static
    {
        return $this->state(fn () => ['mock_exam_question_id' => $question->id]);
    }
}
