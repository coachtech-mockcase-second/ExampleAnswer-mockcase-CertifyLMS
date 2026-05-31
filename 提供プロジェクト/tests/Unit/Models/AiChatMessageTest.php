<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AiChatMessage モデルのリレーション・Cast を検証する Unit テスト。
 * 1 リレーション (conversation) + 主要 cast (role enum / status enum / input_tokens int) を網羅する。
 * factory state: userMessage() / assistantCompleted() / assistantPending() / assistantError() で role・status を制御する。
 */
class AiChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_relation_returns_parent_conversation(): void
    {
        // Arrange
        $conversation = AiChatConversation::factory()->general()->create();
        $message = AiChatMessage::factory()->for($conversation, 'conversation')->userMessage()->create();

        // Act
        $parent = $message->conversation;

        // Assert
        $this->assertTrue($parent->is($conversation));
    }

    public function test_role_cast_is_user_for_user_message(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->userMessage()->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertInstanceOf(AiChatMessageRole::class, $fresh->role, 'role は AiChatMessageRole enum にキャストされるはず');
        $this->assertSame(AiChatMessageRole::User, $fresh->role);
    }

    public function test_role_and_status_casts_for_assistant_completed(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->assistantCompleted()->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertSame(AiChatMessageRole::Assistant, $fresh->role, 'assistant メッセージは role=Assistant のはず');
        $this->assertInstanceOf(AiChatMessageStatus::class, $fresh->status);
        $this->assertSame(AiChatMessageStatus::Completed, $fresh->status, '完了済 assistant メッセージは status=Completed のはず');
    }

    public function test_status_is_error_for_assistant_error(): void
    {
        // Arrange
        $message = AiChatMessage::factory()->assistantError()->create();

        // Act
        $fresh = $message->fresh();

        // Assert
        $this->assertSame(AiChatMessageStatus::Error, $fresh->status, 'エラー応答は status=Error のはず');
    }
}
