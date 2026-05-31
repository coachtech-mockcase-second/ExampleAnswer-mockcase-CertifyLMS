<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MeetingQuotaTransaction モデルのリレーション・Cast を検証する Unit テスト。
 * 主要 2 リレーション (user / grantedBy) + 3 cast (type enum / amount int / occurred_at datetime) を網羅する。
 * 面談回数の増減 (初期付与 / 購入 / 消費 / 返却 / 管理者付与) を記録する台帳モデル。
 */
class MeetingQuotaTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $transaction = MeetingQuotaTransaction::factory()->for($student)->grantedInitial()->create();

        // Act
        $owner = $transaction->user;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_type_cast_converts_to_enum_for_granted_initial(): void
    {
        // Arrange
        $transaction = MeetingQuotaTransaction::factory()->grantedInitial(4)->create();

        // Act
        $fresh = $transaction->fresh();

        // Assert
        $this->assertInstanceOf(MeetingQuotaTransactionType::class, $fresh->type, 'type は MeetingQuotaTransactionType enum にキャストされるはず');
        $this->assertSame(MeetingQuotaTransactionType::GrantedInitial, $fresh->type);
    }

    public function test_consumed_transaction_has_negative_or_consumed_type(): void
    {
        // Arrange
        $transaction = MeetingQuotaTransaction::factory()->consumed()->create();

        // Act
        $fresh = $transaction->fresh();

        // Assert
        $this->assertSame(MeetingQuotaTransactionType::Consumed, $fresh->type, '消費トランザクションは Consumed type を持つはず');
    }

    public function test_amount_and_occurred_at_casts(): void
    {
        // Arrange
        $transaction = MeetingQuotaTransaction::factory()->purchased(5)->create([
            'occurred_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $transaction->fresh();

        // Assert
        $this->assertIsInt($fresh->amount, 'amount は integer にキャストされるはず');
        $this->assertInstanceOf(Carbon::class, $fresh->occurred_at);
    }
}
