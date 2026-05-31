<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SectionQuestion;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionQuestionAttempt>
 */
class SectionQuestionAttemptFactory extends Factory
{
    protected $model = SectionQuestionAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'section_question_id' => SectionQuestion::factory(),
            'attempt_count' => 1,
            'correct_count' => 0,
            'last_is_correct' => false,
            'last_answered_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forQuestion(SectionQuestion $question): static
    {
        return $this->state(fn () => ['section_question_id' => $question->id]);
    }

    public function withAttempts(int $count, int $correct): static
    {
        return $this->state(fn () => [
            'attempt_count' => $count,
            'correct_count' => $correct,
        ]);
    }

    public function lastIs(bool $correct): static
    {
        return $this->state(fn () => ['last_is_correct' => $correct]);
    }

    public function lastAnsweredAt(Carbon $at): static
    {
        return $this->state(fn () => ['last_answered_at' => $at]);
    }
}
