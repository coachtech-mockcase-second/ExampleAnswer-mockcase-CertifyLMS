<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\TermType;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Enrollment モデルの主要リレーション・Scope・Cast・SoftDelete を検証する Unit テスト。
 * 5 主要リレーション + 4 scope (learning / passed / failed / forUser) + 4 cast + SoftDelete を網羅する。
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        // Act
        $owner = $enrollment->user;

        // Assert
        $this->assertTrue($owner->is($student), '所有 student と enrollment->user は一致するはず');
    }

    public function test_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert)->create();

        // Act
        $target = $enrollment->certification;

        // Assert
        $this->assertTrue($target->is($cert), '対象 certification と enrollment->certification は一致するはず');
    }

    public function test_certificate_relation_returns_single_certificate(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->passed()->create();
        $certificate = Certificate::factory()->for($enrollment)->create();

        // Act
        $issued = $enrollment->certificate;

        // Assert
        $this->assertNotNull($issued, 'passed enrollment は Certificate を 1 件持つはず');
        $this->assertTrue($issued->is($certificate));
    }

    public function test_status_logs_relation_returns_history(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        EnrollmentStatusLog::factory()->for($enrollment)->create();
        EnrollmentStatusLog::factory()->for($enrollment)->create();

        // Act
        $logs = $enrollment->statusLogs;

        // Assert
        $this->assertCount(2, $logs, '対象 enrollment の status logs だけが取得されるはず');
    }

    public function test_latest_status_log_returns_only_most_recent(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $older = EnrollmentStatusLog::factory()->for($enrollment)->create([
            'changed_at' => now()->subDays(3),
        ]);
        $newer = EnrollmentStatusLog::factory()->for($enrollment)->create([
            'changed_at' => now()->subDay(),
        ]);

        // Act
        $latest = $enrollment->latestStatusLog;

        // Assert
        $this->assertTrue($latest->is($newer), 'latestStatusLog は changed_at が最大のレコード 1 件を返すはず');
        $this->assertFalse($latest->is($older));
    }

    public function test_scope_learning_filters_only_learning_status(): void
    {
        // Arrange
        $learning = Enrollment::factory()->learning()->create();
        Enrollment::factory()->passed()->create();
        Enrollment::factory()->failed()->create();

        // Act
        $results = Enrollment::learning()->get();

        // Assert
        $this->assertCount(1, $results, 'Learning ステータスのみが scope で抽出されるはず');
        $this->assertTrue($results->first()->is($learning));
    }

    public function test_scope_passed_filters_only_passed_status(): void
    {
        // Arrange
        Enrollment::factory()->learning()->create();
        $passed = Enrollment::factory()->passed()->create();
        Enrollment::factory()->failed()->create();

        // Act
        $results = Enrollment::passed()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($passed));
    }

    public function test_scope_failed_filters_only_failed_status(): void
    {
        // Arrange
        Enrollment::factory()->learning()->create();
        Enrollment::factory()->passed()->create();
        $failed = Enrollment::factory()->failed()->create();

        // Act
        $results = Enrollment::failed()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($failed));
    }

    /**
     * forUser scope の操作者ロール別行絞込みを検証。
     * admin = 全件 / coach = 担当資格の Enrollment のみ / student = 自分の Enrollment のみ。
     */
    public function test_scope_for_user_admin_returns_all_enrollments(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Enrollment::factory()->count(3)->create();

        // Act
        $results = Enrollment::forUser($admin)->get();

        // Assert
        $this->assertCount(3, $results, 'admin は全 enrollment を取得できるはず');
    }

    public function test_scope_for_user_coach_returns_only_assigned_certifications(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $assignedCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
        $assigned = Enrollment::factory()->for($assignedCert)->create();
        Enrollment::factory()->create(); // 未担当 cert、coach 視点では見えないはず

        // Act
        $results = Enrollment::forUser($coach)->get();

        // Assert
        $this->assertCount(1, $results, 'coach は担当資格の enrollment のみ取得できるはず');
        $this->assertTrue($results->first()->is($assigned));
    }

    public function test_scope_for_user_student_returns_only_own_enrollments(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $own = Enrollment::factory()->for($student)->create();
        Enrollment::factory()->create(); // 他人の enrollment、student 視点では見えないはず

        // Act
        $results = Enrollment::forUser($student)->get();

        // Assert
        $this->assertCount(1, $results, 'student は自分の enrollment のみ取得できるはず');
        $this->assertTrue($results->first()->is($own));
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Learning->value,
        ]);

        // Act
        $fresh = $enrollment->fresh();

        // Assert
        $this->assertInstanceOf(
            EnrollmentStatus::class,
            $fresh->status,
            'status カラムは EnrollmentStatus enum にキャストされるはず',
        );
        $this->assertSame(EnrollmentStatus::Learning, $fresh->status);
    }

    public function test_current_term_cast_converts_string_to_enum(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->mockPractice()->create();

        // Act
        $fresh = $enrollment->fresh();

        // Assert
        $this->assertInstanceOf(
            TermType::class,
            $fresh->current_term,
            'current_term カラムは TermType enum にキャストされるはず',
        );
        $this->assertSame(TermType::MockPractice, $fresh->current_term);
    }

    public function test_exam_date_cast_returns_carbon_date(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create([
            'exam_date' => '2026-12-01',
        ]);

        // Act
        $fresh = $enrollment->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->exam_date);
        $this->assertSame('2026-12-01', $fresh->exam_date->toDateString());
    }

    public function test_passed_at_cast_returns_carbon_datetime(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->passed()->create();

        // Act
        $fresh = $enrollment->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->passed_at, 'passed_at は Carbon datetime にキャストされるはず');
    }

    public function test_soft_delete_keeps_record_recoverable_via_with_trashed(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $enrollmentId = $enrollment->id;

        // Act
        $enrollment->delete();

        // Assert
        $this->assertSoftDeleted($enrollment, [], 'enrollments');
        $this->assertNull(
            Enrollment::find($enrollmentId),
            '通常 query では SoftDelete 済みレコードは取得されないはず',
        );
        $this->assertNotNull(
            Enrollment::withTrashed()->find($enrollmentId),
            'withTrashed() で SoftDelete 済みレコードを取得できるはず',
        );
    }
}
