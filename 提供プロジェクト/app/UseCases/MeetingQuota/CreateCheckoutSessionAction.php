<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserStatus;
use App\Exceptions\MeetingQuota\MeetingPackNotPublishedException;
use App\Exceptions\MeetingQuota\UserNotInProgressException;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

/**
 * 受講生の追加面談購入動線で Stripe Checkout Session を発行するユースケース。
 *
 * (1) SKU 公開状態と購入者の受講状態を検証
 * (2) Stripe API で Checkout Session を作成(都度生成型、price_data 動的)
 * (3) pending 状態の Payment を INSERT して Webhook で確定を待つ
 * (4) フロントへ checkout_url + payment_id を返す
 */
final class CreateCheckoutSessionAction
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: string}
     *
     * @throws MeetingPackNotPublishedException
     * @throws UserNotInProgressException
     */
    public function __invoke(User $user, MeetingPack $plan): array
    {
        if ($plan->status !== MeetingPackStatus::Published) {
            throw new MeetingPackNotPublishedException;
        }

        if ($user->status !== UserStatus::InProgress) {
            throw new UserNotInProgressException;
        }

        return DB::transaction(function () use ($user, $plan) {
            $session = $this->stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'jpy',
                        'product_data' => [
                            'name' => $plan->name,
                        ],
                        'unit_amount' => $plan->price,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('dashboard.index'),
                'metadata' => [
                    'user_id' => $user->id,
                    'meeting_pack_id' => $plan->id,
                ],
            ]);

            $payment = Payment::create([
                'user_id' => $user->id,
                'type' => 'extra_meeting_quota',
                'meeting_pack_id' => $plan->id,
                'stripe_checkout_session_id' => $session->id,
                'amount' => $plan->price,
                'quantity' => $plan->meeting_count,
                'status' => PaymentStatus::Pending->value,
            ]);

            return [
                'checkout_url' => $session->url,
                'payment_id' => $payment->id,
            ];
        });
    }
}
