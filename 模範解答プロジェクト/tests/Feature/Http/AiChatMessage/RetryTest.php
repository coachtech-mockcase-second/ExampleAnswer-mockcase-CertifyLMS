<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChatMessage;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class RetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retry_error_message_and_gets_completed_assistant(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('retried OK'));

        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create(['content' => '元の質問']);
        $errorMessage = AiChatMessage::factory()->assistantError()->for($conv, 'conversation')->create();

        $this->actingAs($student)
            ->postJson(route('ai-chat.messages.retry', $errorMessage))
            ->assertOk();

        $latest = $conv->fresh()->messages->last();
        $this->assertSame(AiChatMessageStatus::Completed, $latest->status);
        $this->assertSame('retried OK', $latest->content);
    }

    public function test_completed_message_cannot_be_retried(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('x'));

        $student = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($student)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create();
        $completed = AiChatMessage::factory()->assistantCompleted()->for($conv, 'conversation')->create();

        $this->actingAs($student)
            ->postJson(route('ai-chat.messages.retry', $completed))
            ->assertStatus(422);
    }

    public function test_other_user_cannot_retry(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('x'));

        $owner = User::factory()->student()->inProgress()->create();
        $intruder = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->for($owner)->create();
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create();
        $err = AiChatMessage::factory()->assistantError()->for($conv, 'conversation')->create();

        $this->actingAs($intruder)
            ->postJson(route('ai-chat.messages.retry', $err))
            ->assertForbidden();
    }
}
