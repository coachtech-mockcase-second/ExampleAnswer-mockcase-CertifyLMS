<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\Payment;
use App\UseCases\MeetingQuota\PurchaseQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_purchased_with_quantity_as_amount(): void
    {
        $payment = Payment::factory()->succeeded()->state([
            'quantity' => 5,
            'amount' => 12000,
        ])->create();

        $tx = app(PurchaseQuotaAction::class)($payment);

        $this->assertSame(MeetingQuotaTransactionType::Purchased, $tx->type);
        $this->assertSame(5, $tx->amount);
        $this->assertSame($payment->id, $tx->related_payment_id);
        $this->assertSame($payment->user_id, $tx->user_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'type' => 'purchased',
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }
}
