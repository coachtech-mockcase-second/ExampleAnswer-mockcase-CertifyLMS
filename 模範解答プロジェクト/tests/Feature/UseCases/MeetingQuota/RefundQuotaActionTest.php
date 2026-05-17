<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\User;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserts_refund_transaction(): void
    {
        $user = User::factory()->student()->create();
        $meetingId = (string) Str::ulid();

        $tx = app(RefundQuotaAction::class)($user, $meetingId);

        $this->assertSame(MeetingQuotaTransactionType::Refunded, $tx->type);
        $this->assertSame(1, $tx->amount);
        $this->assertSame($meetingId, $tx->related_meeting_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => 'refunded',
            'amount' => 1,
            'related_meeting_id' => $meetingId,
        ]);
    }
}
