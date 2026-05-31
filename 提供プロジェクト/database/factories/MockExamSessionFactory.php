<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MockExamSessionStatus;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockExamSession>
 */
class MockExamSessionFactory extends Factory
{
    protected $model = MockExamSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $enrollmentId = Enrollment::factory();
        $mockExamId = MockExam::factory()->published();

        return [
            'enrollment_id' => $enrollmentId,
            'mock_exam_id' => $mockExamId,
            'user_id' => function (array $attributes) {
                $enrollment = Enrollment::find($attributes['enrollment_id']);

                return $enrollment?->user_id ?? User::factory()->student()->create()->id;
            },
            'status' => MockExamSessionStatus::NotStarted->value,
            'generated_question_ids' => [],
            'total_questions' => 0,
            'passing_score_snapshot' => 60,
            'started_at' => null,
            'submitted_at' => null,
            'graded_at' => null,
            'canceled_at' => null,
            'total_correct' => null,
            'score_percentage' => null,
            'pass' => null,
        ];
    }

    public function notStarted(): static
    {
        return $this->state(fn () => [
            'status' => MockExamSessionStatus::NotStarted->value,
            'started_at' => null,
            'submitted_at' => null,
            'graded_at' => null,
            'canceled_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => MockExamSessionStatus::InProgress->value,
            'started_at' => now()->subMinutes(10),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => MockExamSessionStatus::Submitted->value,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
        ]);
    }

    public function graded(bool $pass = true, int $totalCorrect = 8, int $totalQuestions = 10): static
    {
        return $this->state(fn () => [
            'status' => MockExamSessionStatus::Graded->value,
            'started_at' => now()->subMinutes(40),
            'submitted_at' => now()->subMinutes(5),
            'graded_at' => now()->subMinutes(5),
            'total_questions' => $totalQuestions,
            'total_correct' => $totalCorrect,
            'score_percentage' => round($totalCorrect / $totalQuestions * 100, 2),
            'pass' => $pass,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => MockExamSessionStatus::Canceled->value,
            'canceled_at' => now(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
        ]);
    }

    public function forMockExam(MockExam $mockExam): static
    {
        return $this->state(fn () => [
            'mock_exam_id' => $mockExam->id,
            'passing_score_snapshot' => $mockExam->passing_score,
        ]);
    }
}
