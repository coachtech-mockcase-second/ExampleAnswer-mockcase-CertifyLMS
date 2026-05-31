<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use App\Services\AiChatPromptBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatPromptBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_user_name_and_section_breadcrumb_when_section_attached(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student->value, 'name' => '山田 太郎']);
        $cert = Certification::factory()->create(['name' => '基本情報技術者']);
        $part = Part::factory()->create([
            'certification_id' => $cert->id,
            'title' => 'データ構造とアルゴリズム',
            'order' => 5,
        ]);
        $chapter = Chapter::factory()->create([
            'part_id' => $part->id,
            'title' => '探索アルゴリズム',
            'order' => 7,
        ]);
        $section = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'title' => 'ハッシュ法',
            'order' => 3,
            'body' => 'ハッシュ法は配列インデックスを計算で求める手法です。',
        ]);
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $conv = AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
        ]);

        $prompt = (new AiChatPromptBuilderService)->build($conv->fresh(), $user);

        // 基本コンテキスト (受講生名 + 資格)
        $this->assertStringContainsString('山田 太郎', $prompt);
        $this->assertStringContainsString('基本情報技術者', $prompt);

        // 教材パンくず (Part > Chapter > Section の order + タイトル)
        $this->assertStringContainsString('Part 5', $prompt);
        $this->assertStringContainsString('データ構造とアルゴリズム', $prompt);
        $this->assertStringContainsString('Chapter 7', $prompt);
        $this->assertStringContainsString('探索アルゴリズム', $prompt);
        $this->assertStringContainsString('Section 3', $prompt);
        $this->assertStringContainsString('ハッシュ法', $prompt);

        // Section.body 本文は Gemini 無料枠保護のためプロンプトに含めない
        $this->assertStringNotContainsString('ハッシュ法は配列インデックス', $prompt);
    }

    public function test_build_uses_default_enrollment_for_certification_context_when_conv_has_no_enrollment(): void
    {
        // 受講生に default_enrollment があるが、会話自体は enrollment_id 無し (全般相談から開始)
        // → defaultEnrollment 経由で資格コンテキストが自動付与される
        $cert = Certification::factory()->create(['name' => '応用情報技術者']);
        $user = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Learning->value,
        ]);
        $user->update(['default_enrollment_id' => $enrollment->id]);

        $conv = AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'enrollment_id' => null,
            'section_id' => null,
        ]);

        $prompt = (new AiChatPromptBuilderService)->build($conv->fresh(), $user->fresh());

        $this->assertStringContainsString('応用情報技術者', $prompt);
    }

    public function test_build_omits_certification_context_when_default_enrollment_is_failed(): void
    {
        $cert = Certification::factory()->create(['name' => '学習中止資格']);
        $user = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $user->id,
            'certification_id' => $cert->id,
            'status' => EnrollmentStatus::Failed->value,
        ]);
        $user->update(['default_enrollment_id' => $enrollment->id]);

        $conv = AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'enrollment_id' => null,
            'section_id' => null,
        ]);

        $prompt = (new AiChatPromptBuilderService)->build($conv->fresh(), $user->fresh());

        $this->assertStringNotContainsString('学習中止資格', $prompt);
    }

    public function test_build_with_no_section_and_no_enrollment_produces_general_prompt(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $conv = AiChatConversation::factory()->create([
            'user_id' => $user->id,
            'section_id' => null,
            'enrollment_id' => null,
        ]);

        $prompt = (new AiChatPromptBuilderService)->build($conv->fresh(), $user);

        $this->assertStringContainsString($user->name, $prompt);
        $this->assertStringNotContainsString('対象資格:', $prompt);
        $this->assertStringNotContainsString('【教材コンテキスト】', $prompt);
    }

    public function test_build_history_excludes_error_status_messages(): void
    {
        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $conv = AiChatConversation::factory()->create(['user_id' => $user->id]);

        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create(['content' => 'q1']);
        AiChatMessage::factory()->assistantCompleted()->for($conv, 'conversation')->create(['content' => 'a1']);
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create(['content' => 'q2']);
        AiChatMessage::factory()->assistantError()->for($conv, 'conversation')->create(['content' => '部分応答']);
        AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create(['content' => 'q3']);

        $history = (new AiChatPromptBuilderService)->buildHistory($conv->fresh());

        $contents = array_column($history, 'content');
        $this->assertContains('q1', $contents);
        $this->assertContains('a1', $contents);
        $this->assertContains('q2', $contents);
        $this->assertContains('q3', $contents);
        $this->assertNotContains('部分応答', $contents);
    }

    public function test_build_history_caps_to_history_window(): void
    {
        config(['ai-chat.history_window' => 5]);

        $user = User::factory()->create(['role' => UserRole::Student->value]);
        $conv = AiChatConversation::factory()->create(['user_id' => $user->id]);

        for ($i = 1; $i <= 10; $i++) {
            AiChatMessage::factory()->userMessage()->for($conv, 'conversation')->create([
                'content' => "msg{$i}",
            ]);
        }

        $history = (new AiChatPromptBuilderService)->buildHistory($conv->fresh());

        $this->assertCount(5, $history);
        // 最新 5 件 (msg6 〜 msg10) が昇順で含まれる
        $this->assertSame(['msg6', 'msg7', 'msg8', 'msg9', 'msg10'], array_column($history, 'content'));
    }
}
