<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_with_conversations_redirects_to_latest(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now()->subDay(),
        ]);
        $latest = AiChatConversation::factory()->for($student)->create([
            'last_message_at' => now(),
        ]);

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertRedirect(route('ai-chat.conversations.show', $latest));
    }

    public function test_student_without_conversations_sees_empty_state(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertViewIs('ai-chat.empty-state');
    }

    public function test_other_users_conversations_are_ignored(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        AiChatConversation::factory()->for($other)->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertViewIs('ai-chat.empty-state');
    }

    public function test_admin_is_forbidden(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }

    public function test_coach_is_forbidden(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }

    public function test_guest_redirects_to_login(): void
    {
        $this->get(route('ai-chat.index'))->assertRedirect(route('login'));
    }

    public function test_graduated_student_is_forbidden_by_active_learning_middleware(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }
}
