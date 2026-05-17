<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certification_id' => Certification::factory()->published(),
            'status' => EnrollmentStatus::Learning->value,
            'exam_date' => now()->addMonths(3)->toDateString(),
            'current_term' => TermType::BasicLearning->value,
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

    public function passed(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Passed->value,
            'passed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => EnrollmentStatus::Failed->value,
        ]);
    }

    public function withoutExamDate(): static
    {
        return $this->state(fn () => [
            'exam_date' => null,
        ]);
    }

    public function mockPractice(): static
    {
        return $this->state(fn () => [
            'current_term' => TermType::MockPractice->value,
        ]);
    }
}
