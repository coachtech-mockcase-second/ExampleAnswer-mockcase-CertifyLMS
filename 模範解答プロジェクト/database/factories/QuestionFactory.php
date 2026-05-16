<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Certification;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        $certificationId = Certification::factory();

        return [
            'certification_id' => $certificationId,
            'section_id' => null,
            'category_id' => function (array $attributes) {
                return QuestionCategory::factory()
                    ->state(['certification_id' => $attributes['certification_id']])
                    ->create()
                    ->id;
            },
            'body' => fake()->sentence().' 何か?',
            'explanation' => fake()->paragraph(),
            'difficulty' => fake()->randomElement(QuestionDifficulty::cases())->value,
            'order' => 0,
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => ContentStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => ['certification_id' => $certification->id]);
    }

    public function forSection(Section $section): static
    {
        return $this->state(function (array $attributes) use ($section) {
            return [
                'section_id' => $section->id,
                'certification_id' => $section->chapter->part->certification_id,
            ];
        });
    }

    public function standalone(): static
    {
        return $this->state(fn () => ['section_id' => null]);
    }

    public function forCategory(QuestionCategory $category): static
    {
        return $this->state(fn () => [
            'category_id' => $category->id,
            'certification_id' => $category->certification_id,
        ]);
    }

    public function withOptions(int $count = 4, int $correctIndex = 0): static
    {
        return $this->afterCreating(function (Question $question) use ($count, $correctIndex) {
            for ($i = 0; $i < $count; $i++) {
                $question->options()->create([
                    'body' => '選択肢 '.($i + 1),
                    'is_correct' => $i === $correctIndex,
                    'order' => $i + 1,
                ]);
            }
        });
    }
}
