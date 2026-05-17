<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExamSession>
 *
 * Enrollment Feature が必要とする最小 Factory。mock-exam Feature 実装時に拡張される。
 */
class MockExamSessionFactory extends Factory
{
    protected $model = MockExamSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'mock_exam_id' => MockExam::factory(),
            'status' => 'not_started',
            'pass' => null,
            'started_at' => null,
            'submitted_at' => null,
            'graded_at' => null,
        ];
    }
}
