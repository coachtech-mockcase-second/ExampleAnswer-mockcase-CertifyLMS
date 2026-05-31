<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Meeting;
use App\Models\MeetingMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingMemo モデルのリレーションを検証する Unit テスト。
 * 1 リレーション (meeting) を網羅する。面談実施後にコーチが残す記録。
 */
class MeetingMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_relation_returns_parent_meeting(): void
    {
        // Arrange
        $meeting = Meeting::factory()->completed()->create();
        $memo = MeetingMemo::factory()->forMeeting($meeting)->create();

        // Act
        $parent = $memo->meeting;

        // Assert
        $this->assertTrue($parent->is($meeting));
    }
}
