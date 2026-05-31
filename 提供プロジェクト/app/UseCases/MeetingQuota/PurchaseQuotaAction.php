<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;

/**
 * Stripe Checkout 完了 Webhook を受けた際に呼ばれる、購入分の面談回数加算ユースケース。
 *
 * Payment.quantity を amount として MeetingQuotaTransaction(type=purchased) を INSERT する。
 * StripeWebhook\HandleAction の DB::transaction 内から呼ばれる前提で、トランザクション境界は呼出側責務。
 */
final class PurchaseQuotaAction
{
    public function __invoke(Payment $payment): MeetingQuotaTransaction
    {
        return MeetingQuotaTransaction::create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
            'occurred_at' => $payment->paid_at ?? now(),
        ]);
    }
}
