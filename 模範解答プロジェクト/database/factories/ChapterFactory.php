<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Chapter;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    protected $model = Chapter::class;

    public function definition(): array
    {
        return [
            'part_id' => Part::factory(),
            'title' => '第'.fake()->numberBetween(1, 20).'章 '.fake()->word(),
            'description' => fake()->optional()->sentence(),
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

    public function forPart(Part $part): static
    {
        return $this->state(fn () => ['part_id' => $part->id]);
    }
}
