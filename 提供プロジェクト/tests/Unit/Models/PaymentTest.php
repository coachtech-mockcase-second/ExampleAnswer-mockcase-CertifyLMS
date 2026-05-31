<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Payment モデルのリレーション・Cast・SoftDelete を検証する Unit テスト。
 * 主要 2 リレーション (user / meetingPack) + 主要 cast (status enum / amount int / paid_at datetime) + SoftDelete を網羅する。
 * 会計監査要件のため SoftDelete を採用している。
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_payer_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->for($student)->succeeded()->create();

        // Act
        $payer = $payment->user;

        // Assert
        $this->assertTrue($payer->is($student));
    }

    public function test_meeting_pack_relation_returns_purchased_pack(): void
    {
        // Arrange
        $pack = MeetingPack::factory()->published()->create();
        $payment = Payment::factory()->for($pack)->succeeded()->create();

        // Act
        $purchased = $payment->meetingPack;

        // Assert
        $this->assertTrue($purchased->is($pack));
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertInstanceOf(PaymentStatus::class, $fresh->status, 'status は PaymentStatus enum にキャストされるはず');
        $this->assertSame(PaymentStatus::Succeeded, $fresh->status);
    }

    public function test_amount_and_paid_at_casts(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $fresh = $payment->fresh();

        // Assert
        $this->assertIsInt($fresh->amount, 'amount は integer にキャストされるはず');
        $this->assertInstanceOf(Carbon::class, $fresh->paid_at, '成功した payment は paid_at が Carbon にキャストされるはず');
    }

    public function test_soft_delete_keeps_record_recoverable(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();
        $paymentId = $payment->id;

        // Act
        $payment->delete();

        // Assert
        $this->assertSoftDeleted('payments', ['id' => $paymentId]);
        $this->assertNull(Payment::find($paymentId), '通常 query では SoftDelete 済みは取得されないはず');
        $this->assertNotNull(
            Payment::withTrashed()->find($paymentId),
            '会計監査のため withTrashed で SoftDelete 済み payment を取得できるはず',
        );
    }
}
