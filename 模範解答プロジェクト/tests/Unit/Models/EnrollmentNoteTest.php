<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EnrollmentNote モデルのリレーションを検証する Unit テスト。
 * 2 リレーション (enrollment / author) を網羅する。コーチが受講生の受講登録に残す指導メモ。
 */
class EnrollmentNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_target_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();

        // Act
        $target = $note->enrollment;

        // Assert
        $this->assertTrue($target->is($enrollment));
    }

    public function test_author_relation_returns_writer_user(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->for($coach, 'author')->create();

        // Act
        $author = $note->author;

        // Assert
        $this->assertTrue($author->is($coach), 'author_user_id で関連付けたコーチが author で取得できるはず');
    }
}
