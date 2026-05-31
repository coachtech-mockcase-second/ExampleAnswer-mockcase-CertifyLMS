<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Webhooks;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SECRET = 'whsec_test_secret_for_phpunit';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.stripe.webhook_secret', self::TEST_SECRET);
    }

    /**
     * Stripe 互換の HMAC-SHA256 署名ヘッダを生成する。
     */
    private function sign(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, self::TEST_SECRET);

        return 't='.$timestamp.',v1='.$signature;
    }

    public function test_returns_400_when_signature_header_missing(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_x']]]);

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        $response->assertStatus(400);
    }

    public function test_returns_400_when_signature_invalid(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_x']]]);

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid'],
            $payload
        );

        $response->assertStatus(400);
    }

    public function test_processes_checkout_completed_with_valid_signature(): void
    {
        $payment = Payment::factory()->pending()->state([
            'stripe_checkout_session_id' => 'cs_test_sig_ok',
            'quantity' => 3,
        ])->create();

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_sig_ok',
                    'payment_intent' => 'pi_test_sig_ok',
                ],
            ],
        ]);

        $response = $this->call(
            'POST',
            route('webhooks.stripe'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $this->sign($payload)],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['received' => true]);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'type' => 'purchased',
            'amount' => 3,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_duplicate_webhook_delivery_is_idempotent(): void
    {
        $payment = Payment::factory()->pending()->state([
            'stripe_checkout_session_id' => 'cs_dup',
            'quantity' => 1,
        ])->create();

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_dup',
                    'payment_intent' => 'pi_dup',
                ],
            ],
        ]);
        $signature = $this->sign($payload);

        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature];

        $first = $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload);
        $second = $this->call('POST', route('webhooks.stripe'), [], [], [], $headers, $payload);

        $first->assertOk();
        $second->assertOk();
        // 1 件のみであることが冪等性の証明
        $this->assertDatabaseCount('meeting_quota_transactions', 1);
    }
}
