<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Certification;
use App\Models\MockExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExam>
 *
 * Enrollment Feature が必要とする最小 Factory。mock-exam Feature 実装時に拡張される。
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
            'passing_score' => 60,
            'is_published' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
