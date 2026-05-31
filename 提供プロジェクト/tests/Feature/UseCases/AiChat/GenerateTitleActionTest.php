<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\UseCases\AiChat\GenerateTitleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class GenerateTitleActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_title_from_first_user_and_assistant_pair(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('ハッシュ法の衝突解決'));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create(['content' => 'ハッシュ法とは?']);
        AiChatMessage::factory()->assistantCompleted()->for($conv, 'conversation')->create(['content' => 'キー値ペア管理...']);

        $title = $this->app->make(GenerateTitleAction::class)($conv->fresh());

        $this->assertSame('ハッシュ法の衝突解決', $title);
    }

    public function test_returns_null_when_only_user_message_exists(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('NEVER'));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create();

        $this->assertNull($this->app->make(GenerateTitleAction::class)($conv->fresh()));
    }

    public function test_returns_null_on_llm_failure(): void
    {
        $fake = FakeLlmRepository::withContent('unused')->failing();
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create();
        AiChatMessage::factory()->assistantCompleted()->for($conv, 'conversation')->create();

        $title = $this->app->make(GenerateTitleAction::class)($conv->fresh());

        $this->assertNull($title);
    }

    public function test_trims_and_caps_title_to_100_chars(): void
    {
        $long = str_repeat('あ', 120).'  ';
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent($long));

        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($user)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create();
        AiChatMessage::factory()->assistantCompleted()->for($conv, 'conversation')->create();

        $title = $this->app->make(GenerateTitleAction::class)($conv->fresh());

        $this->assertNotNull($title);
        $this->assertSame(100, mb_strlen($title));
    }
}
