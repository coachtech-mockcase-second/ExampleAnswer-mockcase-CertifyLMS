<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\UserRole;
use App\Exceptions\AiChat\AiChatLlmFailedException;
use App\Models\AiChatConversation;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\UseCases\AiChatMessage\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversation(): AiChatConversation
    {
        $user = User::factory()->create(['role' => UserRole::Student->value]);

        return AiChatConversation::factory()->for($user)->create();
    }

    public function test_creates_user_and_assistant_messages_on_success(): void
    {
        $fake = FakeLlmRepository::withContent(content: 'こんにちは!', model: 'gemini-2.5-flash-lite');
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $conv = $this->makeConversation();
        $action = $this->app->make(StoreAction::class);

        $result = $action($conv, 'はじめまして');

        $this->assertSame(AiChatMessageRole::User, $result['user_message']->role);
        $this->assertSame('はじめまして', $result['user_message']->content);
        $this->assertSame(AiChatMessageStatus::Completed, $result['user_message']->status);

        $this->assertSame(AiChatMessageRole::Assistant, $result['assistant_message']->role);
        $this->assertSame('こんにちは!', $result['assistant_message']->content);
        $this->assertSame(AiChatMessageStatus::Completed, $result['assistant_message']->status);
        $this->assertSame('gemini-2.5-flash-lite', $result['assistant_message']->model);
        $this->assertSame(100, $result['assistant_message']->input_tokens);
        $this->assertSame(50, $result['assistant_message']->output_tokens);

        $this->assertNotNull($conv->fresh()->last_message_at);
    }

    public function test_marks_assistant_as_error_and_throws_llm_failed_on_api_exception(): void
    {
        $fake = (FakeLlmRepository::withContent('unused'))->failing();
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $conv = $this->makeConversation();
        $action = $this->app->make(StoreAction::class);

        try {
            $action($conv, 'テスト質問');
            $this->fail('Expected AiChatLlmFailedException');
        } catch (AiChatLlmFailedException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame(503, $e->upstreamStatus);
        }

        // assistant メッセージは error 状態で永続化されている
        $assistant = $conv->fresh()->messages
            ->first(fn ($m) => $m->role === AiChatMessageRole::Assistant);
        $this->assertNotNull($assistant);
        $this->assertSame(AiChatMessageStatus::Error, $assistant->status);
        $this->assertNotNull($assistant->error_detail);

        // user メッセージは残る (DB 先行 commit による中断耐性)
        $this->assertCount(2, $conv->fresh()->messages);
    }

    public function test_passes_history_to_llm(): void
    {
        $fake = FakeLlmRepository::withContent('reply');
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $conv = $this->makeConversation();
        $action = $this->app->make(StoreAction::class);

        $action($conv, '1 通目');
        $action($conv, '2 通目');

        // 2 通目送信時には少なくとも 1 通目の user + assistant が history に含まれる想定
        $this->assertNotNull($fake->lastMessages);
        $this->assertGreaterThanOrEqual(3, count($fake->lastMessages));
    }
}
