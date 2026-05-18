<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsumeQuotaActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumes_quota_when_remaining_is_positive(): void
    {
        $user = User::factory()->student()->create(['max_meetings' => 3]);
        $meeting = Meeting::factory()->reserved()->forStudent($user)->create();

        $tx = app(ConsumeQuotaAction::class)($user, $meeting->id);

        $this->assertSame(MeetingQuotaTransactionType::Consumed, $tx->type);
        $this->assertSame(-1, $tx->amount);
        $this->assertSame($meeting->id, $tx->related_meeting_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $user->id,
            'type' => 'consumed',
            'amount' => -1,
            'related_meeting_id' => $meeting->id,
        ]);
    }

    public function test_throws_when_remaining_is_zero(): void
    {
        $user = User::factory()->student()->create(['max_meetings' => 0]);
        $meeting = Meeting::factory()->reserved()->forStudent($user)->create();

        $this->expectException(InsufficientMeetingQuotaException::class);
        app(ConsumeQuotaAction::class)($user, $meeting->id);
    }

    public function test_throws_when_balance_is_already_consumed(): void
    {
        $user = User::factory()->student()->create(['max_meetings' => 1]);
        $first = Meeting::factory()->reserved()->forStudent($user)->create();
        $second = Meeting::factory()->reserved()->forStudent($user)->create();

        app(ConsumeQuotaAction::class)($user, $first->id);

        $this->expectException(InsufficientMeetingQuotaException::class);
        app(ConsumeQuotaAction::class)($user, $second->id);
    }
}
