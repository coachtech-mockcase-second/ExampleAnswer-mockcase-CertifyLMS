<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MockExamAnswer;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExamAnswer>
 */
class MockExamAnswerFactory extends Factory
{
    protected $model = MockExamAnswer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mock_exam_session_id' => MockExamSession::factory(),
            'mock_exam_question_id' => MockExamQuestion::factory(),
            'selected_option_id' => null,
            'selected_option_body' => fake()->sentence(4),
            'is_correct' => false,
            'answered_at' => now(),
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
