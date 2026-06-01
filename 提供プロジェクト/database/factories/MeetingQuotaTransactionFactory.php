<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingQuotaTransaction>
 */
class MeetingQuotaTransactionFactory extends Factory
{
    protected $model = MeetingQuotaTransaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'type' => MeetingQuotaTransactionType::GrantedInitial->value,
            'amount' => 1,
            'related_meeting_id' => null,
            'related_payment_id' => null,
            'granted_by_user_id' => null,
            'note' => null,
            'occurred_at' => now(),
        ];
    }

    public function grantedInitial(int $amount = 4): static
    {
        return $this->state(fn () => [
            'type' => MeetingQuotaTransactionType::GrantedInitial->value,
            'amount' => $amount,
        ]);
    }

    public function purchased(int $amount = 5, ?string $paymentId = null): static
    {
        return $this->state(fn () => [
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => $amount,
            'related_payment_id' => $paymentId,
        ]);
    }

    public function consumed(?string $meetingId = null): static
    {
        return $this->state(fn () => [
            'type' => MeetingQuotaTransactionType::Consumed->value,
            'amount' => -1,
            'related_meeting_id' => $meetingId,
        ]);
    }

    public function refunded(?string $meetingId = null): static
    {
        return $this->state(fn () => [
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
            'related_meeting_id' => $meetingId,
        ]);
    }

    public function adminGrant(User $admin, int $amount = 1, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'type' => MeetingQuotaTransactionType::AdminGrant->value,
            'amount' => $amount,
            'granted_by_user_id' => $admin->id,
            'note' => $reason,
        ]);
    }
}
