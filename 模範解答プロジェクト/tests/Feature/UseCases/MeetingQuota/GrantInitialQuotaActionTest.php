<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\User;
use App\UseCases\MeetingQuota\GrantInitialQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GrantInitialQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_transaction_with_null_admin_when_system_grant(): void
    {
        $user = User::factory()->student()->create();

        $tx = app(GrantInitialQuotaAction::class)($user, 5);

        $this->assertSame(MeetingQuotaTransactionType::GrantedInitial, $tx->type);
        $this->assertSame(5, $tx->amount);
        $this->assertNull($tx->granted_by_user_id);
        $this->assertNull($tx->note);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => 'granted_initial',
            'amount' => 5,
            'granted_by_user_id' => null,
        ]);
    }

    public function test_inserts_transaction_with_admin_when_admin_grant(): void
    {
        $user = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $tx = app(GrantInitialQuotaAction::class)($user, 3, $admin);

        $this->assertSame($admin->id, $tx->granted_by_user_id);
        $this->assertNull($tx->note);
    }

    public function test_inserts_transaction_with_reason_in_note(): void
    {
        $user = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        app(GrantInitialQuotaAction::class)($user, 2, $admin, 'プラン延長');

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'granted_by_user_id' => $admin->id,
            'note' => 'プラン延長',
        ]);
    }

    public function test_throws_when_amount_is_zero(): void
    {
        $user = User::factory()->student()->create();

        $this->expectException(InvalidArgumentException::class);
        app(GrantInitialQuotaAction::class)($user, 0);
    }

    public function test_throws_when_amount_is_negative(): void
    {
        $user = User::factory()->student()->create();

        $this->expectException(InvalidArgumentException::class);
        app(GrantInitialQuotaAction::class)($user, -1);
    }
}
