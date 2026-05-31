<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `invitations:expire` Schedule Command を検証する Feature テスト。
 * 期限切れの pending Invitation を一括 Expired にすること、期限内 / 既処理は変化しないことを網羅する。
 */
class ExpireInvitationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_invitations_past_expiry_are_expired(): void
    {
        // Arrange
        $expiredUser = User::factory()->invited()->create();
        $validUser = User::factory()->invited()->create();
        $expired = Invitation::factory()->forUser($expiredUser)->pending()->create([
            'expires_at' => now()->subDay(),
        ]);
        $stillValid = Invitation::factory()->forUser($validUser)->pending()->create([
            'expires_at' => now()->addDay(),
        ]);

        // Act
        $this->artisan('invitations:expire')->assertExitCode(0);

        // Assert
        $this->assertSame(InvitationStatus::Expired, $expired->fresh()->status, '期限切れ pending は Expired に遷移するはず');
        $this->assertSame(InvitationStatus::Pending, $stillValid->fresh()->status, '期限内 pending は変化しないはず');
    }

    public function test_already_processed_invitations_are_not_affected(): void
    {
        // Arrange
        $accepted = Invitation::factory()->accepted()->create();
        $revoked = Invitation::factory()->revoked()->create();

        // Act
        $this->artisan('invitations:expire')->assertExitCode(0);

        // Assert
        $this->assertSame(InvitationStatus::Accepted, $accepted->fresh()->status, 'accepted は変化しないはず');
        $this->assertSame(InvitationStatus::Revoked, $revoked->fresh()->status, 'revoked は変化しないはず');
    }

    public function test_command_reports_processed_count_in_output(): void
    {
        // Arrange
        $expiredUser = User::factory()->invited()->create();
        Invitation::factory()->forUser($expiredUser)->pending()->create(['expires_at' => now()->subHour()]);

        // Act & Assert
        $this->artisan('invitations:expire')
            ->expectsOutputToContain('期限切れ Invitation を 1 件処理しました。')
            ->assertExitCode(0);
    }
}
