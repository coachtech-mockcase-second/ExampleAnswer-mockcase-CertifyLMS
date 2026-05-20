<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Exceptions\AiChat\AiChatConversationCreationDeniedException;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use App\UseCases\AiChatMessage\StoreAction as MessageStoreAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AI 相談会話を作成するユースケース。
 *
 * - section_id 指定時は Section → Chapter → Part → Certification をたどり、受講生の
 *   Enrollment (status: learning / passed) を引いて enrollment_id を自動補完する
 * - 受講生が当該資格に該当 Enrollment を持たなければ AiChatConversationCreationDeniedException (403) を throw
 * - reuseExisting=true (ウィジェット経路) + section_id ありの場合、既存会話を返却 (新規作成しない)
 * - initialMessage が指定されたら user message + assistant 応答までを MessageStoreAction で一括処理
 *
 * 「資格相談モード」(= section_id 無し + 資格指定) は持たない。資格コンテキストは
 * AiChatPromptBuilderService が user.default_enrollment_id 経由で動的解決する。
 */
final class StoreAction
{
    public function __construct(
        private readonly MessageStoreAction $messageStore,
    ) {}

    /**
     * @return array{conversation: AiChatConversation, was_reused: bool}
     *
     * @throws AiChatConversationCreationDeniedException
     */
    public function __invoke(
        User $user,
        ?string $sectionId,
        ?string $initialMessage,
        bool $reuseExisting = false,
    ): array {
        $section = $this->loadSection($sectionId);

        $enrollmentId = $section !== null
            ? $this->resolveEnrollmentForSection($user, $section)
            : null;

        // ウィジェット経路 + section_id 既存会話があれば再開
        if ($reuseExisting && $section !== null) {
            $existing = AiChatConversation::query()
                ->where('user_id', $user->id)
                ->where('section_id', $section->id)
                ->whereNull('deleted_at')
                ->orderByDesc('last_message_at')
                ->first();
            if ($existing !== null) {
                if ($initialMessage !== null) {
                    ($this->messageStore)($existing, $initialMessage);
                }

                return ['conversation' => $existing->fresh(), 'was_reused' => true];
            }
        }

        $conversation = DB::transaction(function () use ($user, $enrollmentId, $section, $initialMessage) {
            return AiChatConversation::create([
                'user_id' => $user->id,
                'enrollment_id' => $enrollmentId,
                'section_id' => $section?->id,
                'title' => $this->resolveTitle($initialMessage),
                'last_message_at' => now(),
            ]);
        });

        if ($initialMessage !== null) {
            ($this->messageStore)($conversation, $initialMessage);
        }

        return ['conversation' => $conversation->fresh(), 'was_reused' => false];
    }

    private function loadSection(?string $sectionId): ?Section
    {
        if ($sectionId === null) {
            return null;
        }

        return Section::query()->with('chapter.part')->find($sectionId);
    }

    /**
     * Section の所属資格に対する受講生の有効な Enrollment を引き、id を返す。
     * 「有効」= status IN (Learning, Passed)。failed は学習中止のため、振り返り相談を許可しない。
     */
    private function resolveEnrollmentForSection(User $user, Section $section): string
    {
        $certificationId = $section->chapter?->part?->certification_id;
        if ($certificationId === null) {
            throw new AiChatConversationCreationDeniedException;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('certification_id', $certificationId)
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->first();

        if ($enrollment === null) {
            throw new AiChatConversationCreationDeniedException;
        }

        return $enrollment->id;
    }

    /**
     * 初回メッセージから fallback タイトルを生成 (先頭 30 文字、マルチバイト安全)。
     * 初回 assistant 応答完了後に LLM がより意味のあるタイトルに上書きする (\App\UseCases\AiChat\GenerateTitleAction)。
     */
    private function resolveTitle(?string $initialMessage): string
    {
        if ($initialMessage === null || $initialMessage === '') {
            return '新規相談';
        }

        return (string) Str::limit($initialMessage, 30, '');
    }
}
