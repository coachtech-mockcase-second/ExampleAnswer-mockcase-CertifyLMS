<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * AiChatConversation モデルのリレーション・Cast を検証する Unit テスト。
 * 主要 3 リレーション (user / messages / latestMessage) + 1 cast (last_message_at datetime) を網羅する。
 * 受講生の AI 相談会話を表すモデル。
 */
class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_user(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $conversation = AiChatConversation::factory()->for($student)->general()->create();

        // Act
        $owner = $conversation->user;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_messages_relation_returns_attached_messages(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->general()->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->userMessage()->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->assistantCompleted()->create();

        // Act
        $messages = $conversation->messages;

        // Assert
        $this->assertCount(2, $messages);
    }

    public function test_latest_message_returns_most_recent_message(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->general()->create();
        $older = AiChatMessage::factory()->for($conversation, 'conversation')->userMessage()->create(['created_at' => now()->subMinutes(5)]);
        $newer = AiChatMessage::factory()->for($conversation, 'conversation')->assistantCompleted()->create(['created_at' => now()]);

        // Act
        $latest = $conversation->latestMessage;

        // Assert
        $this->assertTrue($latest->is($newer), 'latestMessage は最新の message を返すはず');
    }

    public function test_last_message_at_cast_returns_carbon(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->general()->create([
            'last_message_at' => '2026-05-20 10:00:00',
        ]);

        // Act
        $fresh = $conversation->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->last_message_at);
    }
}
