<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningSession>
 */
class LearningSessionFactory extends Factory
{
    protected $model = LearningSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = now()->subMinutes(30);

        return [
            'user_id' => User::factory()->student(),
            'enrollment_id' => Enrollment::factory(),
            'section_id' => Section::factory(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(15),
            'duration_seconds' => 15 * 60,
            'auto_closed' => false,
        ];
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

    public function forSection(Section $section): static
    {
        return $this->state(fn () => ['section_id' => $section->id]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subMinutes(5),
            'ended_at' => null,
            'duration_seconds' => null,
            'auto_closed' => false,
        ]);
    }

    public function closed(int $durationSeconds = 900): static
    {
        return $this->state(function () use ($durationSeconds) {
            $startedAt = now()->subSeconds($durationSeconds + 60);

            return [
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'auto_closed' => false,
            ];
        });
    }

    public function autoClosed(int $durationSeconds = 3600): static
    {
        return $this->state(function () use ($durationSeconds) {
            $startedAt = now()->subDay();

            return [
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'auto_closed' => true,
            ];
        });
    }

    public function startedOn(\DateTimeInterface $date): static
    {
        return $this->state(fn () => ['started_at' => $date]);
    }
}
