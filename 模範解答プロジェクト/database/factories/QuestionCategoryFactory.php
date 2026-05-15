<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionCategory>
 */
class QuestionCategoryFactory extends Factory
{
    protected $model = QuestionCategory::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'テクノロジー系',
            'マネジメント系',
            'ストラテジ系',
            '基礎理論',
            'アルゴリズム',
            'データベース',
            'ネットワーク',
            'セキュリティ',
        ]);

        return [
            'certification_id' => Certification::factory(),
            'name' => $name.' '.fake()->numberBetween(1, 999),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'sort_order' => fake()->numberBetween(0, 100),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => ['certification_id' => $certification->id]);
    }
}
