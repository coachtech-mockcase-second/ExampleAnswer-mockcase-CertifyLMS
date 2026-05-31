<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Invitation モデルのリレーション・Scope・Cast・isUsable ヘルパ・SoftDelete を検証する Unit テスト。
 * 2 リレーション (user / invitedBy、いずれも withTrashed) + 2 scope (pending / expired) +
 * 5 cast (role / status enum + 3 datetime) + isUsable() + SoftDelete を網羅する。
 * 再招待時に旧 pending を revoke する設計を踏まえ、状態遷移の境界も検証する。
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_invited_user(): void
    {
        // Arrange
        $invited = User::factory()->invited()->create();
        $invitation = Invitation::factory()->forUser($invited)->pending()->create();

        // Act
        $target = $invitation->user;

        // Assert
        $this->assertTrue($target->is($invited), 'invited User と invitation->user は一致するはず');
    }

    public function test_user_relation_resolves_trashed_user(): void
    {
        // Arrange: 招待発行後に対象 User が SoftDelete されたケース
        $invited = User::factory()->invited()->create();
        $invitation = Invitation::factory()->forUser($invited)->pending()->create();
        $invited->delete();

        // Act
        $resolved = $invitation->fresh()->user;

        // Assert
        $this->assertNotNull(
            $resolved,
            'invitation->user は withTrashed が付与されているため SoftDelete 済みでも解決できるはず',
        );
        $this->assertSame($invited->id, $resolved->id);
    }

    public function test_invited_by_relation_returns_admin(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create(['invited_by_user_id' => $admin->id]);

        // Act
        $inviter = $invitation->invitedBy;

        // Assert
        $this->assertTrue($inviter->is($admin), 'invited_by_user_id で関連付けた admin が取得できるはず');
    }

    public function test_scope_pending_filters_only_pending_status(): void
    {
        // Arrange
        $pending = Invitation::factory()->pending()->create();
        Invitation::factory()->accepted()->create();
        Invitation::factory()->revoked()->create();

        // Act
        $results = Invitation::pending()->get();

        // Assert
        $this->assertCount(1, $results, 'Pending ステータスのみが scope で抽出されるはず');
        $this->assertTrue($results->first()->is($pending));
    }

    public function test_scope_expired_returns_pending_invitations_past_expiry(): void
    {
        // Arrange
        $expiredButPending = Invitation::factory()->pending()->create([
            'expires_at' => now()->subHour(),
        ]);
        Invitation::factory()->pending()->create([
            'expires_at' => now()->addDay(),
        ]); // まだ有効

        // Act
        $results = Invitation::expired()->get();

        // Assert
        $this->assertCount(
            1,
            $results,
            'expired scope は status=Pending かつ expires_at <= now の行のみ返すはず',
        );
        $this->assertTrue($results->first()->is($expiredButPending));
    }

    public function test_is_usable_returns_true_for_pending_and_not_yet_expired(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create([
            'expires_at' => now()->addDays(3),
        ]);

        // Act
        $usable = $invitation->isUsable();

        // Assert
        $this->assertTrue($usable, 'Pending + 期限内の招待は使用可能と判定されるはず');
    }

    public function test_is_usable_returns_false_when_already_expired(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create([
            'expires_at' => now()->subSecond(),
        ]);

        // Act
        $usable = $invitation->isUsable();

        // Assert
        $this->assertFalse($usable, 'expires_at が過去の招待は使用不可と判定されるはず');
    }

    public function test_is_usable_returns_false_for_non_pending_status(): void
    {
        // Arrange
        $accepted = Invitation::factory()->accepted()->create([
            'expires_at' => now()->addDays(3),
        ]);
        $revoked = Invitation::factory()->revoked()->create([
            'expires_at' => now()->addDays(3),
        ]);

        // Act & Assert
        $this->assertFalse($accepted->isUsable(), 'accepted の招待は再利用不可');
        $this->assertFalse($revoked->isUsable(), 'revoked の招待は再利用不可');
    }

    public function test_role_cast_converts_string_to_enum(): void
    {
        // Arrange
        $invitation = Invitation::factory()->role(UserRole::Coach)->create();

        // Act
        $fresh = $invitation->fresh();

        // Assert
        $this->assertInstanceOf(UserRole::class, $fresh->role, 'role カラムは UserRole enum にキャストされるはず');
        $this->assertSame(UserRole::Coach, $fresh->role);
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $invitation = Invitation::factory()->pending()->create();

        // Act
        $fresh = $invitation->fresh();

        // Assert
        $this->assertInstanceOf(InvitationStatus::class, $fresh->status);
        $this->assertSame(InvitationStatus::Pending, $fresh->status);
    }

    public function test_datetime_casts_return_carbon_instances(): void
    {
        // Arrange
        $invitation = Invitation::factory()->create([
            'expires_at' => '2026-06-01 10:00:00',
            'accepted_at' => '2026-05-25 12:00:00',
            'revoked_at' => '2026-05-24 09:00:00',
        ]);

        // Act
        $fresh = $invitation->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->expires_at, 'expires_at は Carbon datetime にキャストされるはず');
        $this->assertInstanceOf(Carbon::class, $fresh->accepted_at);
        $this->assertInstanceOf(Carbon::class, $fresh->revoked_at);
    }

    public function test_soft_delete_keeps_record_recoverable(): void
    {
        // Arrange
        $invitation = Invitation::factory()->revoked()->create();
        $invitationId = $invitation->id;

        // Act
        $invitation->delete();

        // Assert
        $this->assertSoftDeleted('invitations', ['id' => $invitationId]);
        $this->assertNull(Invitation::find($invitationId));
        $this->assertNotNull(
            Invitation::withTrashed()->find($invitationId),
            'withTrashed で SoftDelete 済み招待を取得できるはず',
        );
    }
}
