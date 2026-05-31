<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_send_message_and_gets_assistant_response(): void
    {
        $fake = FakeLlmRepository::withContent('こんにちは、何でも聞いてください。');
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conv), [
                'content' => 'はじめまして',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user_message' => ['id', 'role', 'content'],
                'assistant_message' => ['id', 'role', 'content', 'status'],
                'conversation' => ['id', 'last_message_at'],
            ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conv->id,
            'role' => AiChatMessageRole::User->value,
            'content' => 'はじめまして',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conv->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Completed->value,
            'content' => 'こんにちは、何でも聞いてください。',
        ]);
    }

    public function test_other_user_cannot_send_message_in_someone_elses_conversation(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('x'));
        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => 'hi'])
            ->assertForbidden();
    }

    public function test_validation_rejects_empty_content(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('x'));
        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => ''])
            ->assertUnprocessable();
    }

    public function test_rate_limit_returns_429_when_exceeded(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('x'));
        config(['ai-chat.daily_message_limit' => 2]);

        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $this->actingAs($student);
        $this->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => '1'])->assertOk();
        $this->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => '2'])->assertOk();
        $this->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => '3'])
            ->assertStatus(429);
    }

    public function test_gemini_api_failure_returns_502_and_marks_assistant_error(): void
    {
        $fake = FakeLlmRepository::withContent('unused');
        $fake->failing();
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();

        $response = $this->actingAs($student)
            ->postJson(route('ai-chat.conversations.messages.store', $conv), ['content' => 'q']);

        $response->assertStatus(502);

        // user / assistant メッセージは DB に残る
        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conv->id,
            'role' => AiChatMessageRole::Assistant->value,
            'status' => AiChatMessageStatus::Error->value,
        ]);
    }
}
