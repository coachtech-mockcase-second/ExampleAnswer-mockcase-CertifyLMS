<?php

declare(strict_types=1);

namespace App\UseCases\StripeWebhook;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\MeetingQuota\PurchaseQuotaAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stripe Webhook イベントを処理するユースケース。冪等性を保証するため、同一イベントの再送に対しても
 * 二重処理しない設計とする(Stripe の at-least-once delivery 仕様への耐性)。
 *
 * 扱うイベント:
 *   - checkout.session.completed:   Payment を succeeded に遷移 + MeetingQuotaTransaction(purchased) INSERT
 *   - checkout.session.expired:     pending のまま放置された Payment を failed に
 *   - payment_intent.payment_failed: 決済失敗の Payment を failed に
 *
 * 未対応イベントは無視する(将来対応のためログ等を残さず、Webhook 自体は 200 を返す)。
 */
final class HandleAction
{
    public function __construct(
        private readonly PurchaseQuotaAction $purchase,
    ) {}

    /**
     * @param  array{type: string, data: array{object: array<string, mixed>}}  $event
     */
    public function __invoke(array $event): void
    {
        match ($event['type']) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'checkout.session.expired' => $this->handleCheckoutExpired($event),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event),
            default => null,
        };
    }

    /**
     * checkout.session.completed の冪等性 5 ステップ処理:
     *   1. stripe_checkout_session_id をキーに Payment を lockForUpdate で SELECT
     *   2. Payment 未存在なら skip(ログのみ、Stripe 側の操作で発生しうる)
     *   3. status が既に succeeded なら skip(冪等性ガード、二重処理防止)
     *   4. Payment を succeeded に UPDATE(stripe_payment_intent_id / paid_at セット)
     *   5. PurchaseQuotaAction で MeetingQuotaTransaction(purchased) INSERT
     *
     * @param  array{type: string, data: array{object: array<string, mixed>}}  $event
     */
    private function handleCheckoutCompleted(array $event): void
    {
        $session = $event['data']['object'];
        $sessionId = (string) ($session['id'] ?? '');

        if ($sessionId === '') {
            return;
        }

        DB::transaction(function () use ($session, $sessionId) {
            $payment = Payment::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                Log::warning('Stripe webhook: Payment not found', ['session_id' => $sessionId]);

                return;
            }

            if ($payment->status === PaymentStatus::Succeeded) {
                Log::info('Stripe webhook: Payment already succeeded, skipping', ['payment_id' => $payment->id]);

                return;
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded->value,
                'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
                'paid_at' => now(),
            ]);

            ($this->purchase)($payment->fresh());
        });
    }

    /**
     * @param  array{type: string, data: array{object: array<string, mixed>}}  $event
     */
    private function handleCheckoutExpired(array $event): void
    {
        $session = $event['data']['object'];
        $sessionId = (string) ($session['id'] ?? '');

        if ($sessionId === '') {
            return;
        }

        DB::transaction(function () use ($sessionId) {
            Payment::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->where('status', PaymentStatus::Pending->value)
                ->update(['status' => PaymentStatus::Failed->value]);
        });
    }

    /**
     * @param  array{type: string, data: array{object: array<string, mixed>}}  $event
     */
    private function handlePaymentFailed(array $event): void
    {
        $paymentIntent = $event['data']['object'];
        $paymentIntentId = (string) ($paymentIntent['id'] ?? '');

        if ($paymentIntentId === '') {
            return;
        }

        DB::transaction(function () use ($paymentIntentId) {
            // 冪等性ガード: 既に succeeded に遷移済の Payment は failed に巻き戻さない
            // (Stripe at-least-once delivery で completed と failed の到着順が逆転した場合への耐性)
            Payment::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->where('status', PaymentStatus::Pending->value)
                ->update(['status' => PaymentStatus::Failed->value]);
        });
    }
}
