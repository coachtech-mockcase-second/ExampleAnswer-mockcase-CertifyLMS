<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'body' => "## ".fake()->sentence()."\n\n".fake()->paragraph()."\n\n```\n".fake()->word()."\n```",
            'order' => fake()->numberBetween(1, 99),
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

    public function forChapter(Chapter $chapter): static
    {
        return $this->state(fn () => ['chapter_id' => $chapter->id]);
    }

    public function withBody(string $body): static
    {
        return $this->state(fn () => ['body' => $body]);
    }
}
