<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\User;
use App\UseCases\MeetingQuota\AdminGrantQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdminGrantQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_admin_grant_with_admin_id_required(): void
    {
        $target = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $tx = app(AdminGrantQuotaAction::class)($target, 3, $admin, 'トラブル補填');

        $this->assertSame(MeetingQuotaTransactionType::AdminGrant, $tx->type);
        $this->assertSame(3, $tx->amount);
        $this->assertSame($admin->id, $tx->granted_by_user_id);
        $this->assertSame('トラブル補填', $tx->note);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $target->id,
            'type' => 'admin_grant',
            'amount' => 3,
            'granted_by_user_id' => $admin->id,
            'note' => 'トラブル補填',
        ]);
    }

    public function test_allows_nullable_reason(): void
    {
        $target = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $tx = app(AdminGrantQuotaAction::class)($target, 1, $admin);

        $this->assertNull($tx->note);
    }

    public function test_throws_when_amount_is_zero(): void
    {
        $target = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $this->expectException(InvalidArgumentException::class);
        app(AdminGrantQuotaAction::class)($target, 0, $admin);
    }
}
