<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `App\Events\ChatMessageSent` の broadcast 設定とペイロードを検証する。
 *
 * - `attachments` フィールドを **含まない** (E-2 撤回)
 * - PrivateChannel("chat-room.{id}") を返す
 */
class ChatMessageSentEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_broadcast_payload_has_no_attachments_field(): void
    {
        $sender = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($sender)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        $message = ChatMessage::factory()
            ->for($room, 'chatRoom')
            ->create(['sender_user_id' => $sender->id, 'body' => 'hello']);

        $event = new ChatMessageSent($message);
        $payload = $event->broadcastWith();

        $this->assertArrayNotHasKey('attachments', $payload);
        $this->assertSame($message->id, $payload['id']);
        $this->assertSame($message->chat_room_id, $payload['chat_room_id']);
        $this->assertSame('hello', $payload['body']);
        $this->assertSame($sender->id, $payload['sender_user_id']);
        $this->assertSame($sender->name, $payload['sender_name']);
        $this->assertSame('student', $payload['sender_role']);
    }

    public function test_broadcasts_on_private_channel_per_room(): void
    {
        $message = ChatMessage::factory()
            ->for(ChatRoom::factory()->for(Enrollment::factory()), 'chatRoom')
            ->create();

        $event = new ChatMessageSent($message);
        $channel = $event->broadcastOn();

        $this->assertSame("private-chat-room.{$message->chat_room_id}", $channel->name);
    }
}
