<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningHourTarget>
 */
class LearningHourTargetFactory extends Factory
{
    protected $model = LearningHourTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'target_total_hours' => 100,
        ];
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => ['enrollment_id' => $enrollment->id]);
    }

    public function hours(int $hours): static
    {
        return $this->state(fn () => ['target_total_hours' => $hours]);
    }
}
