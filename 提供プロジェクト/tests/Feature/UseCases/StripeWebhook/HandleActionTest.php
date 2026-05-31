<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\StripeWebhook;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\UseCases\StripeWebhook\HandleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandleActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{type: string, data: array{object: array<string, mixed>}}
     */
    private function checkoutCompletedEvent(string $sessionId, string $paymentIntentId): array
    {
        return [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'payment_intent' => $paymentIntentId,
                ],
            ],
        ];
    }

    public function test_checkout_completed_skips_when_payment_not_found(): void
    {
        $event = $this->checkoutCompletedEvent('cs_test_missing', 'pi_test_x');

        app(HandleAction::class)($event);

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_checkout_completed_promotes_pending_to_succeeded_and_inserts_transaction(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->pending()->state([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_abc',
            'quantity' => 5,
        ])->create();

        app(HandleAction::class)($this->checkoutCompletedEvent('cs_test_abc', 'pi_test_abc'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame('pi_test_abc', $payment->stripe_payment_intent_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'type' => 'purchased',
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_checkout_completed_is_idempotent_when_already_succeeded(): void
    {
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->succeeded()->state([
            'user_id' => $student->id,
            'stripe_checkout_session_id' => 'cs_test_abc',
            'stripe_payment_intent_id' => 'pi_test_abc',
            'quantity' => 5,
        ])->create();
        $originalPaidAt = $payment->paid_at;

        app(HandleAction::class)($this->checkoutCompletedEvent('cs_test_abc', 'pi_test_abc'));
        app(HandleAction::class)($this->checkoutCompletedEvent('cs_test_abc', 'pi_test_abc'));

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertEquals(
            $originalPaidAt->toDateTimeString(),
            $payment->paid_at->toDateTimeString(),
            'paid_at should not be re-touched on idempotent replay',
        );
    }

    public function test_checkout_session_expired_marks_pending_payment_failed(): void
    {
        $payment = Payment::factory()->pending()->state(['stripe_checkout_session_id' => 'cs_test_exp'])->create();

        app(HandleAction::class)([
            'type' => 'checkout.session.expired',
            'data' => ['object' => ['id' => 'cs_test_exp']],
        ]);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_checkout_session_expired_does_not_touch_succeeded_payment(): void
    {
        $payment = Payment::factory()->succeeded()->state(['stripe_checkout_session_id' => 'cs_test_already'])->create();

        app(HandleAction::class)([
            'type' => 'checkout.session.expired',
            'data' => ['object' => ['id' => 'cs_test_already']],
        ]);

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_payment_intent_failed_marks_payment_failed(): void
    {
        $payment = Payment::factory()->pending()->state([
            'stripe_payment_intent_id' => 'pi_test_fail',
            'stripe_checkout_session_id' => 'cs_test_fail',
        ])->create();

        app(HandleAction::class)([
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_test_fail']],
        ]);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_payment_intent_failed_does_not_rollback_succeeded_payment(): void
    {
        // Stripe at-least-once delivery で completed と failed の到着順が逆転した場合への耐性:
        // 既に succeeded に遷移した Payment は failed に巻き戻されない
        $payment = Payment::factory()->succeeded()->state([
            'stripe_payment_intent_id' => 'pi_test_already_ok',
            'stripe_checkout_session_id' => 'cs_test_already_ok',
        ])->create();

        app(HandleAction::class)([
            'type' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'pi_test_already_ok']],
        ]);

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_unknown_event_is_ignored(): void
    {
        app(HandleAction::class)([
            'type' => 'customer.created',
            'data' => ['object' => ['id' => 'cus_test_x']],
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }
}
