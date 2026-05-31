<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_memo_for_reserved_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $memo = app(UpsertMemoAction::class)($meeting, '事前メモ');

        $this->assertSame('事前メモ', $memo->body);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '事前メモ',
        ]);
    }

    public function test_updates_existing_memo_for_completed_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create();
        MeetingMemo::factory()->forMeeting($meeting)->create(['body' => '旧メモ']);

        $memo = app(UpsertMemoAction::class)($meeting, '更新後');

        $this->assertSame('更新後', $memo->body);
        $this->assertSame(1, MeetingMemo::where('meeting_id', $meeting->id)->count());
    }

    public function test_throws_when_meeting_is_canceled(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $canceled = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create();

        $this->expectException(MeetingStatusTransitionException::class);
        app(UpsertMemoAction::class)($canceled, 'メモ');
    }
}
