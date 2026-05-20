<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeLlm(): void
    {
        $this->app->instance(LlmRepositoryInterface::class, FakeLlmRepository::withContent('AI 応答です'));
    }

    public function test_student_can_create_general_conversation_without_section(): void
    {
        $this->bindFakeLlm();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)
            ->post(route('ai-chat.conversations.store'), []);

        $response->assertRedirect();
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_student_cannot_create_conversation_for_section_of_unenrolled_certification(): void
    {
        $this->bindFakeLlm();
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->create();
        $part = Part::factory()->create(['certification_id' => $cert->id]);
        $chapter = Chapter::factory()->create(['part_id' => $part->id]);
        $section = Section::factory()->create(['chapter_id' => $chapter->id]);

        $response = $this->actingAs($student)
            ->post(route('ai-chat.conversations.store'), ['section_id' => $section->id]);

        $response->assertForbidden();
        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    public function test_admin_is_forbidden_from_creating_conversation(): void
    {
        $this->bindFakeLlm();
        $admin = User::factory()->admin()->inProgress()->create();

        $this->actingAs($admin)
            ->post(route('ai-chat.conversations.store'))
            ->assertForbidden();
    }

    public function test_widget_source_with_section_reuses_existing_conversation(): void
    {
        $this->bindFakeLlm();
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->create();
        Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $part = Part::factory()->create(['certification_id' => $cert->id]);
        $chapter = Chapter::factory()->create(['part_id' => $part->id]);
        $section = Section::factory()->create(['chapter_id' => $chapter->id]);

        $existing = \App\Models\AiChatConversation::factory()->create([
            'user_id' => $student->id,
            'section_id' => $section->id,
        ]);

        $this->actingAs($student)
            ->post(route('ai-chat.conversations.store'), [
                'section_id' => $section->id,
                'source' => 'widget',
            ])
            ->assertRedirect(route('ai-chat.conversations.show', $existing));

        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }
}
