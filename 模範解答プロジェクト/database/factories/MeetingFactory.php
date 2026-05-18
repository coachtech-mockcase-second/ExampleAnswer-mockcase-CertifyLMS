<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $student = User::factory()->student();
        $coach = User::factory()->coach();
        $enrollment = Enrollment::factory()->for($student, 'user')->learning();

        return [
            'enrollment_id' => $enrollment,
            'coach_id' => $coach,
            'student_id' => $student,
            'scheduled_at' => $this->roundedFuture(days: fake()->numberBetween(1, 14), hour: fake()->numberBetween(9, 20)),
            'status' => MeetingStatus::Reserved->value,
            'topic' => fake()->sentence(8),
            'meeting_url_snapshot' => 'https://meet.example.com/'.fake()->lexify('??????'),
            'canceled_by_user_id' => null,
            'canceled_at' => null,
            'completed_at' => null,
            'meeting_quota_transaction_id' => null,
        ];
    }

    public function reserved(): static
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Reserved->value,
            'canceled_by_user_id' => null,
            'canceled_at' => null,
            'completed_at' => null,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Canceled->value,
            'canceled_at' => now()->subDay(),
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Completed->value,
            'scheduled_at' => now()->subDays(fake()->numberBetween(1, 30))->setMinute(0)->setSecond(0),
            'completed_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function inPast(): static
    {
        return $this->state(fn () => [
            'scheduled_at' => now()->subDays(fake()->numberBetween(1, 30))->setMinute(0)->setSecond(0),
        ]);
    }

    public function inFuture(): static
    {
        return $this->state(fn () => [
            'scheduled_at' => $this->roundedFuture(days: fake()->numberBetween(1, 14), hour: fake()->numberBetween(9, 20)),
        ]);
    }

    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'coach_id' => $coach->id,
            'meeting_url_snapshot' => $coach->meeting_url ?? 'https://meet.example.com/'.fake()->lexify('??????'),
        ]);
    }

    public function forStudent(User $student): static
    {
        return $this->state(fn () => [
            'student_id' => $student->id,
        ]);
    }

    public function forEnrollment(Enrollment $enrollment): static
    {
        return $this->state(fn () => [
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->user_id,
        ]);
    }

    private function roundedFuture(int $days, int $hour): Carbon
    {
        return now()
            ->addDays($days)
            ->setHour($hour)
            ->setMinute(0)
            ->setSecond(0)
            ->setMicrosecond(0);
    }
}
