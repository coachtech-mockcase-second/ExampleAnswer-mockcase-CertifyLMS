<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Gemini に渡すシステムプロンプト + 過去メッセージ履歴を組み立てる Service。
 *
 * - build(): config テンプレ + 動的変数 (受講生名 / 資格名 / Section 本文) を埋め込む
 * - buildHistory(): 過去 N 件 (history_window) のメッセージを OpenAI 形式に整形 (error 状態は除外)
 *
 * 資格コンテキストの解決順序: 会話の enrollment_id → user.default_enrollment_id → なし (全般相談)
 * `learning` / `passed` 状態の Enrollment のみコンテキスト注入対象 (failed は除外)。
 */
final class AiChatPromptBuilderService
{
    /**
     * システムプロンプトを組み立てる。
     */
    public function build(AiChatConversation $conversation, User $user): string
    {
        $template = (string) config('ai-chat.system_prompt_template', '');

        $conversation->loadMissing([
            'enrollment.certification',
            'section.chapter.part',
        ]);

        $enrollment = $this->resolveEnrollmentForContext($conversation, $user);

        $certificationContext = $enrollment?->certification?->name !== null
            ? "対象資格: {$enrollment->certification->name}"
            : '';

        $sectionContext = $this->buildSectionContext($conversation);

        return strtr($template, [
            '{user_name}' => $user->name,
            '{certification_context}' => $certificationContext,
            '{section_context}' => $sectionContext,
        ]);
    }

    /**
     * 過去メッセージを OpenAI 形式の history に整形する。
     *
     * - error 状態のメッセージは履歴から除外 (LLM への文脈混入を防ぐ)
     * - 直近 `history_window` 件のみ取得 (Gemini のコンテキストサイズ保護、運用上 20 件で十分)
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function buildHistory(AiChatConversation $conversation): array
    {
        $windowSize = (int) config('ai-chat.history_window', 20);

        return $conversation->messages()
            ->where('status', '!=', AiChatMessageStatus::Error->value)
            ->orderByDesc('created_at')
            ->limit($windowSize)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn ($m) => [
                'role' => $m->role instanceof AiChatMessageRole ? $m->role->value : (string) $m->role,
                'content' => (string) $m->content,
            ])
            ->all();
    }

    /**
     * 資格コンテキストとして採用する Enrollment を解決する。
     *
     * 優先順位:
     *   1. 会話レコードの enrollment_id (= Section 自動補完 or 過去保存分)
     *   2. ユーザーの default_enrollment_id (受講生が「いつもこの資格」と設定した値)
     *
     * いずれも status が learning / passed のもののみ採用する (failed = 学習中止資格は除外)。
     */
    private function resolveEnrollmentForContext(AiChatConversation $conversation, User $user): ?Enrollment
    {
        $candidate = $conversation->enrollment ?? $user->defaultEnrollment;
        if ($candidate === null) {
            return null;
        }

        $candidate->loadMissing('certification');
        $isActive = in_array($candidate->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true);

        return $isActive ? $candidate : null;
    }

    private function buildSectionContext(AiChatConversation $conversation): string
    {
        $section = $conversation->section;
        if ($section === null) {
            return '';
        }

        return "【教材コンテキスト】\nセクション: {$section->title}\n\n{$section->body}";
    }
}
