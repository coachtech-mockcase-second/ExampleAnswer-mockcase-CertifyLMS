<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AI 相談メッセージのテスト・シーダー用 Factory。
 *
 * - 既定: user role / Completed status / 本文 50 文字程度
 * - state userMessage / assistantCompleted / assistantPending / assistantError でバリエーション
 *
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User,
            'content' => fake()->realText(80),
            'status' => AiChatMessageStatus::Completed,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
            'error_detail' => null,
        ];
    }

    public function userMessage(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'model' => null,
            'input_tokens' => null,
            'output_tokens' => null,
            'response_time_ms' => null,
        ]);
    }

    public function assistantCompleted(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->realText(200),
            'model' => 'gemini-2.5-flash',
            'input_tokens' => fake()->numberBetween(50, 800),
            'output_tokens' => fake()->numberBetween(50, 800),
            'response_time_ms' => fake()->numberBetween(200, 3000),
        ]);
    }

    public function assistantPending(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Pending,
            'content' => '',
        ]);
    }

    public function assistantError(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'error_detail' => 'Gemini API HTTP 503',
        ]);
    }
}
