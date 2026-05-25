<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\CertificationCoachAttached;
use App\Events\CertificationCoachDetached;
use App\Listeners\SyncChatMembersOnCoachAssignmentChanged;
use App\Models\Certification;
use App\Models\User;
use App\Services\ChatMemberSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * SyncChatMembersOnCoachAssignmentChanged リスナーが担当変更イベントを受けて
 * ChatMemberSyncService::syncForCertification に委譲することを検証する Unit テスト。
 * Attached / Detached 両イベントで同じ同期処理が走ることを Mockery で網羅する。
 */
class SyncChatMembersOnCoachAssignmentChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_delegates_sync_for_attached_event(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $service = Mockery::mock(ChatMemberSyncService::class);
        $service->shouldReceive('syncForCertification')
            ->once()
            ->with(Mockery::on(fn (Certification $c) => $c->is($cert)));
        $listener = new SyncChatMembersOnCoachAssignmentChanged($service);

        // Act
        $listener->handle(new CertificationCoachAttached($cert, $coach, $admin));

        // Assert: Mockery の shouldReceive(once) が満たされることで委譲を保証
        $this->assertTrue(true);
    }

    public function test_handle_delegates_sync_for_detached_event(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $service = Mockery::mock(ChatMemberSyncService::class);
        $service->shouldReceive('syncForCertification')->once();
        $listener = new SyncChatMembersOnCoachAssignmentChanged($service);

        // Act
        $listener->handle(new CertificationCoachDetached($cert, $coach, $admin));

        // Assert
        $this->assertTrue(true);
    }
}
