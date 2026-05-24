<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChatMessage モデルのリレーションを検証する Unit テスト。
 * 2 リレーション (chatRoom / sender) を網羅する。
 */
class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_room_relation_returns_parent_room(): void
    {
        // Arrange
        $room = ChatRoom::factory()->create();
        $message = ChatMessage::factory()->for($room)->create();

        // Act
        $parent = $message->chatRoom;

        // Assert
        $this->assertTrue($parent->is($room));
    }

    public function test_sender_relation_returns_author_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $message = ChatMessage::factory()->for($student, 'sender')->create();

        // Act
        $sender = $message->sender;

        // Assert
        $this->assertTrue($sender->is($student), 'sender_user_id で関連付けた送信者が取得できるはず');
    }
}
