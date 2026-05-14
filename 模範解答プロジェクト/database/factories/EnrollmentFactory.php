<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certification_id' => Certification::factory()->published(),
            'status' => EnrollmentStatus::Learning->value,
            'exam_date' => now()->addMonths(3)->toDateString(),
            'current_term' => 'basic_learning',
            'completion_requested_at' => null,
            'passed_at' => null,
        ];
    }

    public function learning(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Learning->value,
            'passed_at' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Paused->value,
        ]);
    }

    public function passed(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => now(),
            'completion_requested_at' => now()->subDay(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Failed->value,
        ]);
    }
}
