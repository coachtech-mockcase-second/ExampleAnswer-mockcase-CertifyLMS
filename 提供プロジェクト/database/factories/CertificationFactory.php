<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                '基本情報技術者試験',
                '応用情報技術者試験',
                'TOEIC',
                '日商簿記2級',
                'PMP',
                'AWS Certified Solutions Architect',
            ]),
            'category_id' => CertificationCategory::factory(),
            'difficulty' => fake()->randomElement(CertificationDifficulty::cases())->value,
            'description' => fake()->paragraph(),
            'status' => CertificationStatus::Draft->value,
            'created_by_user_id' => User::factory()->admin(),
            'updated_by_user_id' => function (array $attributes) {
                return $attributes['created_by_user_id'];
            },
            'published_at' => null,
            'archived_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => CertificationStatus::Draft->value,
            'published_at' => null,
            'archived_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => CertificationStatus::Published->value,
            'published_at' => now(),
            'archived_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => CertificationStatus::Archived->value,
            'published_at' => now()->subMonths(3),
            'archived_at' => now(),
        ]);
    }

    public function forCategory(CertificationCategory $category): static
    {
        return $this->state(fn () => [
            'category_id' => $category->id,
        ]);
    }
}
