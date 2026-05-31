<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\Models\Section;
use App\Models\SectionQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionQuestion>
 */
class SectionQuestionFactory extends Factory
{
    protected $model = SectionQuestion::class;

    public function definition(): array
    {
        $sectionId = Section::factory();

        return [
            'section_id' => $sectionId,
            'category_id' => function (array $attributes) {
                $section = Section::with('chapter.part')->find($attributes['section_id']);
                $certificationId = $section?->chapter?->part?->certification_id
                    ?? Certification::factory()->create()->id;

                return QuestionCategory::factory()
                    ->state(['certification_id' => $certificationId])
                    ->create()
                    ->id;
            },
            'body' => fake()->sentence().' 何か?',
            'explanation' => fake()->paragraph(),
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

    public function forSection(Section $section): static
    {
        return $this->state(fn () => ['section_id' => $section->id]);
    }

    public function forCategory(QuestionCategory $category): static
    {
        return $this->state(fn () => ['category_id' => $category->id]);
    }

    public function withOptions(int $count = 4, int $correctIndex = 0): static
    {
        return $this->afterCreating(function (SectionQuestion $question) use ($count, $correctIndex) {
            for ($i = 0; $i < $count; $i++) {
                $question->options()->create([
                    'body' => '選択肢 '.($i + 1),
                    'is_correct' => $i === $correctIndex,
                    'order' => $i,
                ]);
            }
        });
    }
}
