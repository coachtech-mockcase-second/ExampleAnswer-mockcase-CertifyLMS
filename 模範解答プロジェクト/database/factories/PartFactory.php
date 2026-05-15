<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        return [
            'certification_id' => Certification::factory(),
            'title' => '第'.fake()->numberBetween(1, 10).'部 '.fake()->word(),
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

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => ['certification_id' => $certification->id]);
    }
}
