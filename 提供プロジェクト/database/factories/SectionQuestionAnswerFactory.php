<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnswerSource;
use App\Models\SectionQuestion;
use App\Models\SectionQuestionAnswer;
use App\Models\SectionQuestionOption;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionQuestionAnswer>
 */
class SectionQuestionAnswerFactory extends Factory
{
    protected $model = SectionQuestionAnswer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'section_question_id' => SectionQuestion::factory(),
            'selected_option_id' => null,
            'selected_option_body' => '選択肢サンプル',
            'is_correct' => false,
            'source' => AnswerSource::SectionQuiz->value,
            'answered_at' => now(),
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

    public function forOption(SectionQuestionOption $option): static
    {
        return $this->state(fn () => [
            'selected_option_id' => $option->id,
            'selected_option_body' => $option->body,
            'is_correct' => $option->is_correct,
        ]);
    }

    public function correct(): static
    {
        return $this->state(fn () => ['is_correct' => true]);
    }

    public function incorrect(): static
    {
        return $this->state(fn () => ['is_correct' => false]);
    }

    public function source(AnswerSource $source): static
    {
        return $this->state(fn () => ['source' => $source->value]);
    }

    public function answeredOn(Carbon $at): static
    {
        return $this->state(fn () => ['answered_at' => $at]);
    }
}
