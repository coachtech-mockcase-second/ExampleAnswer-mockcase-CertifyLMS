<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    protected $model = Certification::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            '基本情報技術者試験',
            '応用情報技術者試験',
            'TOEIC',
            '日商簿記2級',
            'PMP',
            'AWS Certified Solutions Architect',
        ]);

        return [
            'code' => 'CERT-'.Str::upper(Str::random(10)),
            'category_id' => CertificationCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'description' => fake()->paragraph(),
            'difficulty' => fake()->randomElement(CertificationDifficulty::cases())->value,
            'passing_score' => fake()->numberBetween(50, 80),
            'total_questions' => fake()->numberBetween(40, 100),
            'exam_duration_minutes' => fake()->randomElement([60, 90, 120, 150, 180]),
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
}
