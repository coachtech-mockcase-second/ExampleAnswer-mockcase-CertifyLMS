<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_returns_zero_when_no_sessions(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $summary = app(StreakService::class)->calculate($student);

        $this->assertSame(0, $summary->currentStreak);
        $this->assertSame(0, $summary->longestStreak);
        $this->assertNull($summary->lastActiveDate);
    }

    public function test_calculate_counts_consecutive_days(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        foreach (range(0, 2) as $daysAgo) {
            LearningSession::factory()
                ->forUser($student)
                ->forEnrollment($enrollment)
                ->state(['started_at' => now()->subDays($daysAgo)->setTime(12, 0)])
                ->create();
        }

        $summary = app(StreakService::class)->calculate($student);

        $this->assertSame(3, $summary->currentStreak);
        $this->assertSame(3, $summary->longestStreak);
        $this->assertNotNull($summary->lastActiveDate);
    }

    public function test_calculate_breaks_streak_when_gap_exists(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->state(['started_at' => now()->subDays(5)->setTime(12, 0)])
            ->create();
        LearningSession::factory()
            ->forUser($student)
            ->forEnrollment($enrollment)
            ->state(['started_at' => now()->setTime(12, 0)])
            ->create();

        $summary = app(StreakService::class)->calculate($student);

        $this->assertSame(1, $summary->currentStreak);
    }
}
