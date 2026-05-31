<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Exceptions\AiChat\AiChatConversationCreationDeniedException;
use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\UseCases\AiChat\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmRepository;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakeLlm(string $content = 'AI 応答です'): FakeLlmRepository
    {
        $fake = FakeLlmRepository::withContent($content);
        $this->app->instance(LlmRepositoryInterface::class, $fake);

        return $fake;
    }

    private function makeSectionForCertification(Certification $cert): Section
    {
        $part = Part::factory()->create(['certification_id' => $cert->id]);
        $chapter = Chapter::factory()->create(['part_id' => $part->id]);

        return Section::factory()->create(['chapter_id' => $chapter->id]);
    }

    public function test_creates_general_conversation_without_section_or_enrollment(): void
    {
        $this->bindFakeLlm();
        $user = User::factory()->create(['role' => UserRole::Student->value]);

        $action = $this->app->make(StoreAction::class);
        $result = $action($user, sectionId: null, initialMessage: null);

        $this->assertFalse($result['was_reused']);
        $this->assertNull($result['conversation']->section_id);
        $this->assertNull($result['conversation']->enrollment_id);
        $this->assertSame('新規相談', $result['conversation']->title);
    }

    public function test_auto_resolves_enrollment_id_when_section_is_given(): void
    {
        $this->bindFakeLlm();
        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $cert = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $section = $this->makeSectionForCertification($cert);

        $action = $this->app->make(StoreAction::class);
        $result = $action($user, sectionId: $section->id, initialMessage: null);

        $this->assertSame($section->id, $result['conversation']->section_id);
        $this->assertSame($enrollment->id, $result['conversation']->enrollment_id);
    }

    public function test_rejects_when_user_has_no_enrollment_for_section_cert(): void
    {
        $this->bindFakeLlm();
        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $cert = Certification::factory()->create();
        // 別の資格に enrollment があるが、Section が指す資格には未登録
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $section = $this->makeSectionForCertification($cert);

        $action = $this->app->make(StoreAction::class);

        $this->expectException(AiChatConversationCreationDeniedException::class);
        $action($user, sectionId: $section->id, initialMessage: null);
    }

    public function test_rejects_when_enrollment_status_is_failed(): void
    {
        $this->bindFakeLlm();
        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $cert = Certification::factory()->create();
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Failed->value,
        ]);
        $section = $this->makeSectionForCertification($cert);

        $action = $this->app->make(StoreAction::class);

        $this->expectException(AiChatConversationCreationDeniedException::class);
        $action($user, sectionId: $section->id, initialMessage: null);
    }

    public function test_reuses_existing_conversation_when_widget_path_with_same_section(): void
    {
        $this->bindFakeLlm();
        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $cert = Certification::factory()->create();
        Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $section = $this->makeSectionForCertification($cert);

        $existing = AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'section_id' => $section->id,
        ]);

        $action = $this->app->make(StoreAction::class);
        $result = $action($user, sectionId: $section->id, initialMessage: null, reuseExisting: true);

        $this->assertTrue($result['was_reused']);
        $this->assertSame($existing->id, $result['conversation']->id);
        // 新規作成されないことを確認
        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_creates_user_and_assistant_messages_when_initial_message_given(): void
    {
        // タイトル自動生成を無効化して fallback タイトル (先頭 30 文字) の挙動を検証
        config(['ai-chat.title_generation_enabled' => false]);
        $this->bindFakeLlm(content: '回答です');
        $user = User::factory()->create(['role' => UserRole::Student->value]);

        $action = $this->app->make(StoreAction::class);
        $result = $action($user, sectionId: null, initialMessage: 'こんにちは AI');

        $conv = $result['conversation']->fresh('messages');
        $this->assertCount(2, $conv->messages);
        $this->assertSame('こんにちは AI', $conv->messages[0]->content);
        $this->assertSame('回答です', $conv->messages[1]->content);
        $this->assertSame('こんにちは AI', $conv->title);
    }
}
