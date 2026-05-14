<?php

namespace Database\Factories;

use App\Models\CertificationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificationCategory>
 */
class CertificationCategoryFactory extends Factory
{
    protected $model = CertificationCategory::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'IT 系',
            '語学',
            'ビジネス',
            '会計・金融',
            'マネジメント',
            'デザイン',
        ]).' '.Str::upper(Str::random(4));

        return [
            'name' => $name,
            'slug' => 'cat-'.Str::lower(Str::random(10)),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function tech(): static
    {
        return $this->state(fn () => [
            'name' => 'IT 系',
            'slug' => 'tech-'.Str::lower(Str::random(6)),
            'sort_order' => 10,
        ]);
    }

    public function language(): static
    {
        return $this->state(fn () => [
            'name' => '語学',
            'slug' => 'language-'.Str::lower(Str::random(6)),
            'sort_order' => 20,
        ]);
    }
}
