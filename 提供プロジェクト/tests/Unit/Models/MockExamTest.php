<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MockExam モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 5 リレーション (certification / createdBy / updatedBy / mockExamQuestions / sessions) +
 * 2 scope (published / forCertification) + 4 cast (is_published bool / passing_score int / order int / published_at datetime)
 * を網羅する。MockExam は is_published フラグで状態管理するため SoftDelete は不採用。
 */
class MockExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_certification_relation_returns_parent_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $mockExam = MockExam::factory()->forCertification($cert)->create();

        // Act
        $parent = $mockExam->certification;

        // Assert
        $this->assertTrue($parent->is($cert), '親 certification と mockExam->certification は一致するはず');
    }

    public function test_created_by_and_updated_by_return_admin_user(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        // Act
        $creator = $mockExam->createdBy;
        $updater = $mockExam->updatedBy;

        // Assert
        $this->assertTrue($creator->is($admin));
        $this->assertTrue($updater->is($admin));
    }

    public function test_mock_exam_questions_relation_returns_attached_questions(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();
        MockExamQuestion::factory()->for($mockExam)->create();
        MockExamQuestion::factory()->for($mockExam)->create();
        MockExamQuestion::factory()->create(); // 別 mockExam の question、混入しないはず

        // Act
        $questions = $mockExam->mockExamQuestions;

        // Assert
        $this->assertCount(2, $questions, '対象 mockExam の問題のみが取得されるはず');
    }

    public function test_sessions_relation_returns_attached_sessions(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();
        MockExamSession::factory()->for($mockExam)->create();
        MockExamSession::factory()->for($mockExam)->create();

        // Act
        $sessions = $mockExam->sessions;

        // Assert
        $this->assertCount(2, $sessions, '対象 mockExam のセッションのみが取得されるはず');
    }

    public function test_scope_published_filters_only_published(): void
    {
        // Arrange
        $published = MockExam::factory()->published()->create();
        MockExam::factory()->draft()->create();

        // Act
        $results = MockExam::published()->get();

        // Assert
        $this->assertCount(1, $results, 'is_published = true の mockExam のみが取得されるはず');
        $this->assertTrue($results->first()->is($published));
    }

    public function test_scope_for_certification_filters_by_certification_id(): void
    {
        // Arrange
        $targetCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $targetMockExam = MockExam::factory()->forCertification($targetCert)->create();
        MockExam::factory()->forCertification($otherCert)->create();

        // Act
        $results = MockExam::forCertification($targetCert->id)->get();

        // Assert
        $this->assertCount(1, $results, '指定した certification_id の mockExam のみが取得されるはず');
        $this->assertTrue($results->first()->is($targetMockExam));
    }

    public function test_is_published_cast_returns_boolean(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->create(['is_published' => 1]);

        // Act
        $fresh = $mockExam->fresh();

        // Assert
        $this->assertIsBool($fresh->is_published, 'is_published は boolean にキャストされるはず');
        $this->assertTrue($fresh->is_published);
    }

    public function test_integer_casts_return_int(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->create([
            'passing_score' => '70',
            'order' => '5',
        ]);

        // Act
        $fresh = $mockExam->fresh();

        // Assert
        $this->assertIsInt($fresh->passing_score);
        $this->assertIsInt($fresh->order);
        $this->assertSame(70, $fresh->passing_score);
        $this->assertSame(5, $fresh->order);
    }

    public function test_published_at_cast_returns_carbon_datetime(): void
    {
        // Arrange
        $mockExam = MockExam::factory()->published()->create();

        // Act
        $fresh = $mockExam->fresh();

        // Assert
        $this->assertInstanceOf(
            Carbon::class,
            $fresh->published_at,
            'published_at は Carbon datetime にキャストされるはず',
        );
    }
}
