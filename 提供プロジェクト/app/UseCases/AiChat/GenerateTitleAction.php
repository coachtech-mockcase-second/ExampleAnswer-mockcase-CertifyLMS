<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Models\AiChatConversation;
use App\Repositories\Contracts\LlmRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * 会話の初回 (user, assistant completed) ペアからタイトルを LLM 生成する Action。
 *
 * - 初回ペアが揃わない場合は null を返す (呼出側は fallback タイトル維持)
 * - LLM 呼出失敗時も null を返す (受講生体験を阻害しない副作用扱い、ログのみ)
 * - 成功時の戻り値は trim + 100 文字 mb_substr で安全化済
 */
final class GenerateTitleAction
{
    public function __construct(
        private readonly LlmRepositoryInterface $llm,
    ) {}

    public function __invoke(AiChatConversation $conversation): ?string
    {
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $firstUser = $messages->first(fn ($m) => $m->role === AiChatMessageRole::User);
        $firstCompletedAssistant = $messages->first(
            fn ($m) => $m->role === AiChatMessageRole::Assistant
                && $m->status === AiChatMessageStatus::Completed,
        );

        if ($firstUser === null || $firstCompletedAssistant === null) {
            return null;
        }

        $systemPrompt = (string) config('ai-chat.title_generation_prompt', '');
        $userPayload = "ユーザー質問: {$firstUser->content}\n\nAI 応答: {$firstCompletedAssistant->content}";

        try {
            $response = $this->llm->chat(
                systemPrompt: $systemPrompt,
                messages: [['role' => AiChatMessageRole::User->value, 'content' => $userPayload]],
            );
        } catch (AiChatLlmApiException $e) {
            Log::channel('ai-chat')->warning('ai-chat title generation failed', [
                'conversation_id' => $conversation->id,
                'error_message' => $e->getMessage(),
            ]);

            return null;
        }

        $title = trim($response->content);
        if ($title === '') {
            return null;
        }

        return (string) mb_substr($title, 0, 100);
    }
}
