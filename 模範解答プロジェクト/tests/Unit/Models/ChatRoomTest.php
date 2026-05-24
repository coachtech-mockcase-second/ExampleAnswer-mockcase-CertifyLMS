<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ChatRoom モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 主要 4 リレーション (enrollment / members / messages / latestMessage) + 1 scope (forUser) + 1 cast (last_message_at datetime) を網羅する。
 */
class ChatRoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_relation_returns_owner_enrollment(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->learning()->create();
        $room = ChatRoom::factory()->for($enrollment)->create();

        // Act
        $owner = $room->enrollment;

        // Assert
        $this->assertTrue($owner->is($enrollment));
    }

    public function test_members_relation_returns_attached_members(): void
    {
        // Arrange
        $room = ChatRoom::factory()->create();
        ChatMember::factory()->for($room)->create();
        ChatMember::factory()->for($room)->create();

        // Act
        $members = $room->members;

        // Assert
        $this->assertCount(2, $members);
    }

    public function test_messages_relation_returns_attached_messages(): void
    {
        // Arrange
        $room = ChatRoom::factory()->create();
        ChatMessage::factory()->for($room)->create();
        ChatMessage::factory()->for($room)->create();

        // Act
        $messages = $room->messages;

        // Assert
        $this->assertCount(2, $messages);
    }

    public function test_scope_for_user_returns_rooms_user_belongs_to(): void
    {
        // Arrange
        $user = User::factory()->student()->create();
        $myRoom = ChatRoom::factory()->create();
        ChatMember::factory()->for($myRoom)->for($user)->create();
        ChatRoom::factory()->create();

        // Act
        $results = ChatRoom::forUser($user)->get();

        // Assert
        $this->assertCount(1, $results, 'user がメンバーとして所属する room のみが取得されるはず');
        $this->assertTrue($results->first()->is($myRoom));
    }

    public function test_last_message_at_cast_returns_carbon(): void
    {
        // Arrange
        $room = ChatRoom::factory()->create(['last_message_at' => '2026-05-20 10:00:00']);

        // Act
        $fresh = $room->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->last_message_at);
    }
}
