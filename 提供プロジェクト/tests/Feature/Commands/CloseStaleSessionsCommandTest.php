<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\LearningSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `learning:close-stale-sessions` Schedule Command を検証する Feature テスト。
 * max_session_seconds を超過した open 学習セッションを auto_closed=true で一括クローズすること、
 * 新しい open セッションは影響を受けないことを網羅する。
 */
class CloseStaleSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_open_sessions_are_force_closed(): void
    {
        // Arrange
        $stale = LearningSession::factory()->open()->create([
            'started_at' => now()->subDays(30),
        ]);
        $fresh = LearningSession::factory()->open()->create([
            'started_at' => now()->subMinute(),
        ]);

        // Act
        $this->artisan('learning:close-stale-sessions')->assertExitCode(0);

        // Assert
        $staleFresh = $stale->fresh();
        $this->assertNotNull($staleFresh->ended_at, '滞留 open セッションは ended_at がセットされるはず');
        $this->assertTrue($staleFresh->auto_closed, '強制クローズは auto_closed=true で記録されるはず');
        $this->assertNull($fresh->fresh()->ended_at, '新しい open セッションは変化しないはず');
    }

    public function test_already_closed_sessions_are_not_affected(): void
    {
        // Arrange
        $closed = LearningSession::factory()->closed()->create([
            'started_at' => now()->subDays(30),
        ]);
        $originalEndedAt = $closed->ended_at;

        // Act
        $this->artisan('learning:close-stale-sessions')->assertExitCode(0);

        // Assert
        $this->assertEquals(
            $originalEndedAt->toIso8601String(),
            $closed->fresh()->ended_at->toIso8601String(),
            '既に closed されたセッションは ended_at が上書きされないはず',
        );
    }

    public function test_command_reports_closed_count_in_output(): void
    {
        // Arrange
        LearningSession::factory()->open()->create(['started_at' => now()->subDays(30)]);

        // Act & Assert
        $this->artisan('learning:close-stale-sessions')
            ->expectsOutputToContain('Closed 1 stale learning sessions.')
            ->assertExitCode(0);
    }
}
