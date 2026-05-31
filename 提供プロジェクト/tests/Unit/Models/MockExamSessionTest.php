<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MockExamSessionStatus;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamAnswer;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MockExamSession モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 4 リレーション (mockExam / enrollment / user / answers) + 主要 scope 3 (graded / forUser / forEnrollment) +
 * 主要 cast (status enum / generated_question_ids array / pass bool / score_percentage decimal / started_at datetime) を網羅する。
 */
class MockExamSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_exam_relation_returns_target_mock_exam(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();
        $session = MockExamSession::factory()->forMockExam($mockExam)->inProgress()->create();

        // Act
        $target = $session->mockExam;

        // Assert
        $this->assertTrue($target->is($mockExam));
    }

    public function test_user_relation_returns_owner_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $session = MockExamSession::factory()->forUser($student)->inProgress()->create();

        // Act
        $owner = $session->user;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_answers_relation_returns_attached_answers(): void
    {
        // Arrange
        $session = MockExamSession::factory()->submitted()->create();
        MockExamAnswer::factory()->for($session, 'mockExamSession')->create();
        MockExamAnswer::factory()->for($session, 'mockExamSession')->create();

        // Act
        $answers = $session->answers;

        // Assert
        $this->assertCount(2, $answers);
    }

    public function test_scope_graded_filters_only_graded_sessions(): void
    {
        // Arrange
        $graded = MockExamSession::factory()->graded()->create();
        MockExamSession::factory()->inProgress()->create();

        // Act
        $results = MockExamSession::graded()->get();

        // Assert
        $this->assertCount(1, $results, 'Graded ステータスのセッションのみが抽出されるはず');
        $this->assertTrue($results->first()->is($graded));
    }

    public function test_scope_for_enrollment_filters_by_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $own = MockExamSession::factory()->forEnrollment($enrollment)->inProgress()->create();
        MockExamSession::factory()->inProgress()->create();

        // Act
        $results = MockExamSession::forEnrollment($enrollment)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($own));
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $session = MockExamSession::factory()->graded()->create();

        // Act
        $fresh = $session->fresh();

        // Assert
        $this->assertInstanceOf(MockExamSessionStatus::class, $fresh->status, 'status は MockExamSessionStatus enum にキャストされるはず');
        $this->assertSame(MockExamSessionStatus::Graded, $fresh->status);
    }

    public function test_generated_question_ids_cast_returns_array(): void
    {
        // Arrange
        $session = MockExamSession::factory()->inProgress()->create([
            'generated_question_ids' => ['q1', 'q2', 'q3'],
        ]);

        // Act
        $fresh = $session->fresh();

        // Assert
        $this->assertIsArray($fresh->generated_question_ids, 'generated_question_ids は array にキャストされるはず');
        $this->assertSame(['q1', 'q2', 'q3'], $fresh->generated_question_ids);
    }

    public function test_pass_and_datetime_casts(): void
    {
        // Arrange
        $session = MockExamSession::factory()->graded(pass: true)->create();

        // Act
        $fresh = $session->fresh();

        // Assert
        $this->assertIsBool($fresh->pass, 'pass は boolean にキャストされるはず');
        $this->assertTrue($fresh->pass);
        $this->assertInstanceOf(Carbon::class, $fresh->graded_at, 'graded_at は Carbon datetime にキャストされるはず');
    }
}
