<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailExpiredEnrollmentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_learning_enrollments_past_exam_date_are_marked_failed(): void
    {
        $expired1 = Enrollment::factory()->learning()->create(['exam_date' => now()->subDay()->toDateString()]);
        $expired2 = Enrollment::factory()->learning()->create(['exam_date' => now()->subDays(7)->toDateString()]);
        $futureExam = Enrollment::factory()->learning()->create(['exam_date' => now()->addDay()->toDateString()]);

        $this->artisan('enrollments:fail-expired')
            ->assertExitCode(0)
            ->expectsOutputToContain('Failed 2 expired enrollments.');

        $this->assertSame(EnrollmentStatus::Failed, $expired1->fresh()->status);
        $this->assertSame(EnrollmentStatus::Failed, $expired2->fresh()->status);
        $this->assertSame(EnrollmentStatus::Learning, $futureExam->fresh()->status);
    }

    public function test_enrollment_without_exam_date_is_skipped(): void
    {
        $noExamDate = Enrollment::factory()->learning()->create(['exam_date' => null]);

        $this->artisan('enrollments:fail-expired')->assertExitCode(0);

        $this->assertSame(EnrollmentStatus::Learning, $noExamDate->fresh()->status);
    }

    public function test_passed_or_failed_enrollments_are_not_affected_even_if_exam_date_expired(): void
    {
        $alreadyPassed = Enrollment::factory()->passed()->create(['exam_date' => now()->subDay()->toDateString()]);
        $alreadyFailed = Enrollment::factory()->failed()->create(['exam_date' => now()->subDay()->toDateString()]);

        $this->artisan('enrollments:fail-expired')->assertExitCode(0);

        $this->assertSame(EnrollmentStatus::Passed, $alreadyPassed->fresh()->status);
        $this->assertSame(EnrollmentStatus::Failed, $alreadyFailed->fresh()->status);
    }

    public function test_status_log_records_system_auto_fail_with_reason(): void
    {
        $enrollment = Enrollment::factory()->learning()->create(['exam_date' => now()->subDay()->toDateString()]);

        $this->artisan('enrollments:fail-expired')->assertExitCode(0);

        $this->assertDatabaseHas('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Failed->value,
            'changed_by_user_id' => null,
            'changed_reason' => '試験日超過による自動失敗',
        ]);
    }
}
