<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SectionProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionProgress>
 */
class SectionProgressFactory extends Factory
{
    protected $model = SectionProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'section_id' => Section::factory(),
            'completed_at' => now(),
        ];
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => ['enrollment_id' => $enrollment->id]);
    }

    public function forSection(Section $section): static
    {
        return $this->state(fn () => ['section_id' => $section->id]);
    }

    public function completedNow(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }

    public function completedDaysAgo(int $days): static
    {
        return $this->state(fn () => ['completed_at' => now()->subDays($days)]);
    }
}
