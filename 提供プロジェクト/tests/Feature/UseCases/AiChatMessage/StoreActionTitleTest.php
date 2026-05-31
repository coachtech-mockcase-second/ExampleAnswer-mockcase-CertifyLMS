<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChatMessage;

use App\Models\AiChatConversation;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\UseCases\AiChatMessage\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class StoreActionTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_message_pair_triggers_title_auto_generation(): void
    {
        config(['ai-chat.title_generation_enabled' => true]);
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('LLM 生成タイトル'));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create(['title' => 'fallback タイトル']);

        $this->app->make(StoreAction::class)($conv, '質問');

        $this->assertSame('LLM 生成タイトル', $conv->fresh()->title);
    }

    public function test_second_message_does_not_change_title(): void
    {
        config(['ai-chat.title_generation_enabled' => true]);
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('別のタイトル'));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create(['title' => 'fallback']);
        $action = $this->app->make(StoreAction::class);

        $action($conv, '1 通目');
        $titleAfter1 = $conv->fresh()->title;
        $action($conv, '2 通目');
        $titleAfter2 = $conv->fresh()->title;

        $this->assertSame($titleAfter1, $titleAfter2);
    }

    public function test_disabling_title_generation_keeps_fallback_title(): void
    {
        config(['ai-chat.title_generation_enabled' => false]);
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('生成タイトル'));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create(['title' => 'fallback']);

        $this->app->make(StoreAction::class)($conv, '質問');

        $this->assertSame('fallback', $conv->fresh()->title);
    }
}
