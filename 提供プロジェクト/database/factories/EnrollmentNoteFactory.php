<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentNote>
 */
class EnrollmentNoteFactory extends Factory
{
    protected $model = EnrollmentNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'coach_user_id' => User::factory()->state(['role' => UserRole::Coach->value]),
            'body' => fake()->realText(200),
        ];
    }
}
