<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_refund_transaction(): void
    {
        $user = User::factory()->student()->create();
        $meeting = Meeting::factory()->canceled()->forStudent($user)->create();

        $tx = app(RefundQuotaAction::class)($user, $meeting->id);

        $this->assertSame(MeetingQuotaTransactionType::Refunded, $tx->type);
        $this->assertSame(1, $tx->amount);
        $this->assertSame($meeting->id, $tx->related_meeting_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => 'refunded',
            'amount' => 1,
            'related_meeting_id' => $meeting->id,
        ]);
    }
}
