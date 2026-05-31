<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\SectionQuestion;
use App\Models\SectionQuestionAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SectionQuestionAttempt モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 2 リレーション (user / sectionQuestion) + 主要 scope 2 (forUser / lastIs) + 4 cast (attempt_count / correct_count / last_is_correct / last_answered_at) を網羅する。
 * 同一 question への複数回挑戦の集計を保持するモデル。
 */
class SectionQuestionAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $attempt = SectionQuestionAttempt::factory()->for($user)->create();

        // Act
        $owner = $attempt->user;

        // Assert
        $this->assertTrue($owner->is($user));
    }

    public function test_section_question_relation_returns_target_question(): void
    {
        // Arrange
        $question = SectionQuestion::factory()->published()->create();
        $attempt = SectionQuestionAttempt::factory()->for($question, 'sectionQuestion')->create();

        // Act
        $target = $attempt->sectionQuestion;

        // Assert
        $this->assertTrue($target->is($question));
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $own = SectionQuestionAttempt::factory()->for($student)->create();
        SectionQuestionAttempt::factory()->create();

        // Act
        $results = SectionQuestionAttempt::forUser($student)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($own));
    }

    public function test_scope_last_is_filters_by_last_is_correct_boolean(): void
    {
        // Arrange
        $lastCorrect = SectionQuestionAttempt::factory()->create(['last_is_correct' => true]);
        SectionQuestionAttempt::factory()->create(['last_is_correct' => false]);

        // Act
        $results = SectionQuestionAttempt::lastIs(true)->get();

        // Assert
        $this->assertCount(1, $results, '直近回答が正解のレコードのみが取得されるはず');
        $this->assertTrue($results->first()->is($lastCorrect));
    }

    public function test_integer_and_boolean_casts(): void
    {
        // Arrange
        $attempt = SectionQuestionAttempt::factory()->create([
            'attempt_count' => '5',
            'correct_count' => '3',
            'last_is_correct' => 1,
        ]);

        // Act
        $fresh = $attempt->fresh();

        // Assert
        $this->assertIsInt($fresh->attempt_count);
        $this->assertSame(5, $fresh->attempt_count);
        $this->assertIsInt($fresh->correct_count);
        $this->assertIsBool($fresh->last_is_correct);
        $this->assertTrue($fresh->last_is_correct);
    }

    public function test_last_answered_at_cast_returns_carbon(): void
    {
        // Arrange
        $attempt = SectionQuestionAttempt::factory()->create([
            'last_answered_at' => '2026-05-20 14:00:00',
        ]);

        // Act
        $fresh = $attempt->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->last_answered_at);
    }
}
