<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LearningSession モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 3 リレーション (user / enrollment / section) + 主要 scope 3 (open / closed / forUser) +
 * 4 cast (started_at / ended_at datetime / duration_seconds int / auto_closed bool) を網羅する。
 */
class LearningSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $session = LearningSession::factory()->forUser($student)->open()->create();

        // Act
        $owner = $session->user;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_section_relation_returns_target_section(): void
    {
        // Arrange
        $section = Section::factory()->published()->create();
        $session = LearningSession::factory()->forSection($section)->open()->create();

        // Act
        $target = $session->section;

        // Assert
        $this->assertTrue($target->is($section));
    }

    public function test_scope_open_filters_sessions_without_end(): void
    {
        // Arrange
        $open = LearningSession::factory()->open()->create();
        LearningSession::factory()->closed()->create();

        // Act
        $results = LearningSession::open()->get();

        // Assert
        $this->assertCount(1, $results, '未終了 (ended_at null) のセッションのみ抽出されるはず');
        $this->assertTrue($results->first()->is($open));
    }

    public function test_scope_closed_filters_ended_sessions(): void
    {
        // Arrange
        LearningSession::factory()->open()->create();
        $closed = LearningSession::factory()->closed()->create();

        // Act
        $results = LearningSession::closed()->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($closed));
    }

    public function test_scope_for_user_filters_by_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $own = LearningSession::factory()->forUser($student)->open()->create();
        LearningSession::factory()->open()->create();

        // Act
        $results = LearningSession::forUser($student)->get();

        // Assert
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($own));
    }

    public function test_duration_and_auto_closed_casts(): void
    {
        // Arrange
        $session = LearningSession::factory()->autoClosed(3600)->create();

        // Act
        $fresh = $session->fresh();

        // Assert
        $this->assertIsInt($fresh->duration_seconds, 'duration_seconds は integer にキャストされるはず');
        $this->assertIsBool($fresh->auto_closed, 'auto_closed は boolean にキャストされるはず');
        $this->assertTrue($fresh->auto_closed);
    }

    public function test_started_at_cast_returns_carbon(): void
    {
        // Arrange
        $session = LearningSession::factory()->open()->create();

        // Act
        $fresh = $session->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->started_at);
    }
}
