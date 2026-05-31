<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ChatMessageSent ブロードキャストイベントの broadcastOn / broadcastAs / broadcastWith を検証する Unit テスト。
 * Pusher 経由で当該 ChatRoom の Private Channel に送る payload 構造を網羅する。
 */
class ChatMessageSentTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_on_targets_chat_room_private_channel(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $event = new ChatMessageSent($message);

        // Act
        $channel = $event->broadcastOn();

        // Assert
        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame("private-chat-room.{$message->chat_room_id}", $channel->name, 'ChatRoom 単位の Private Channel (private- prefix 付与) に送るはず');
    }

    public function test_broadcast_as_returns_event_alias(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $event = new ChatMessageSent($message);

        // Act
        $alias = $event->broadcastAs();

        // Assert
        $this->assertSame('ChatMessageSent', $alias);
    }

    public function test_broadcast_with_includes_message_payload(): void
    {
        // Arrange
        $message = ChatMessage::factory()->fromStudent()->create();
        $event = new ChatMessageSent($message);

        // Act
        $payload = $event->broadcastWith();

        // Assert
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame($message->chat_room_id, $payload['chat_room_id']);
        $this->assertSame($message->sender_user_id, $payload['sender_user_id']);
        $this->assertArrayHasKey('created_at', $payload);
    }
}
