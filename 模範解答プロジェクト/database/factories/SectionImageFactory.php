<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Section;
use App\Models\SectionImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SectionImage>
 */
class SectionImageFactory extends Factory
{
    protected $model = SectionImage::class;

    public function definition(): array
    {
        $ulid = (string) Str::ulid();

        return [
            'section_id' => Section::factory(),
            'path' => "section-images/{$ulid}.png",
            'original_filename' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'size_bytes' => fake()->numberBetween(1024, 2_000_000),
        ];
    }

    public function forSection(Section $section): static
    {
        return $this->state(fn () => ['section_id' => $section->id]);
    }
}
