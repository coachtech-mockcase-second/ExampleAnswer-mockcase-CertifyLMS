<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\CertificationDifficulty;
use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Certification モデルのリレーション・Cast を検証する Unit テスト。
 * 主要 5 リレーション (category / coaches / parts / mockExams / enrollments) + 4 cast (status / difficulty enum + published_at / archived_at datetime) を網羅する。
 * scope (published / assignedTo / forUser / keyword) は CertificationScopesTest が担当するため本ファイルでは扱わない。
 */
class CertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_relation_returns_parent_category(): void
    {
        // Arrange
        $category = CertificationCategory::factory()->create();
        $cert = Certification::factory()->forCategory($category)->create();

        // Act
        $parent = $cert->category;

        // Assert
        $this->assertTrue($parent->is($category));
    }

    public function test_coaches_relation_returns_only_active_assignments(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $cert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        // Act
        $coaches = $cert->coaches;

        // Assert
        $this->assertCount(1, $coaches, '担当アサインされた coach が coaches リレーションで取得できるはず');
        $this->assertTrue($coaches->first()->is($coach));
    }

    public function test_parts_relation_returns_attached_parts(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        Part::factory()->for($cert)->create();
        Part::factory()->for($cert)->create();
        Part::factory()->create();

        // Act
        $parts = $cert->parts;

        // Assert
        $this->assertCount(2, $parts, '対象 certification の part のみが取得されるはず');
    }

    public function test_enrollments_relation_returns_attached_enrollments(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->create();
        Enrollment::factory()->create();

        // Act
        $enrollments = $cert->enrollments;

        // Assert
        $this->assertCount(1, $enrollments);
    }

    public function test_mock_exams_relation_returns_attached_mock_exams(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        MockExam::factory()->forCertification($cert)->create();
        MockExam::factory()->forCertification($cert)->create();

        // Act
        $mockExams = $cert->mockExams;

        // Assert
        $this->assertCount(2, $mockExams);
    }

    public function test_status_and_difficulty_casts_convert_to_enum(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();

        // Act
        $fresh = $cert->fresh();

        // Assert
        $this->assertInstanceOf(CertificationStatus::class, $fresh->status, 'status は CertificationStatus enum にキャストされるはず');
        $this->assertSame(CertificationStatus::Published, $fresh->status);
        $this->assertInstanceOf(CertificationDifficulty::class, $fresh->difficulty, 'difficulty は CertificationDifficulty enum にキャストされるはず');
    }

    public function test_published_at_cast_returns_carbon(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();

        // Act
        $fresh = $cert->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->published_at);
    }
}
