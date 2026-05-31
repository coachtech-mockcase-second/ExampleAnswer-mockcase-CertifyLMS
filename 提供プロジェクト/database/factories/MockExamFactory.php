<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExam>
 */
class MockExamFactory extends Factory
{
    protected $model = MockExam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certification_id' => Certification::factory()->published(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'order' => 0,
            'passing_score' => 60,
            'is_published' => false,
            'published_at' => null,
            'created_by_user_id' => User::factory()->admin(),
            'updated_by_user_id' => User::factory()->admin(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => ['certification_id' => $certification->id]);
    }

    public function passingScore(int $score): static
    {
        return $this->state(fn () => ['passing_score' => $score]);
    }
}
