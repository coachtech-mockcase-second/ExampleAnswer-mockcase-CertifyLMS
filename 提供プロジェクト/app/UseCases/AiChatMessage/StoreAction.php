<?php

declare(strict_types=1);

namespace App\UseCases\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatLlmApiException;
use App\Exceptions\AiChat\AiChatLlmFailedException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Repositories\Contracts\LlmRepositoryInterface;
use App\Services\AiChatPromptBuilderService;
use App\UseCases\AiChat\GenerateTitleAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 同期 AI 相談メッセージ送信ユースケース。
 *
 * フロー:
 *   Transaction A (先行 commit):
 *     1. user メッセージを INSERT (status=completed)
 *     2. assistant プレースホルダを INSERT (status=pending、空 content)
 *   Transaction 外:
 *     3. LlmRepositoryInterface::chat() を呼出
 *   結果書込:
 *     4a. 成功 → assistant を Completed + metadata に UPDATE → 初回ペアならタイトル LLM 生成試行
 *     4b. 失敗 → assistant を Error に UPDATE + ログ + 502 例外を throw
 *     5. conversation の last_message_at を now() に UPDATE
 *
 * Transaction A を先行 commit させる理由: LLM 呼出失敗時に user メッセージ + error 状態の
 * assistant メッセージを DB に残し、受講生が「再送信」ボタンで再生成できる前提を満たすため。
 * トランザクション内で例外が伝播するとロールバックでメッセージごと消えてしまうのを避ける。
 */
final class StoreAction
{
    public function __construct(
        private readonly LlmRepositoryInterface $llm,
        private readonly AiChatPromptBuilderService $promptBuilder,
        private readonly GenerateTitleAction $titleGenerator,
    ) {}

    /**
     * @return array{user_message: AiChatMessage, assistant_message: AiChatMessage}
     *
     * @throws AiChatLlmFailedException
     */
    public function __invoke(AiChatConversation $conversation, string $content): array
    {
        $user = $conversation->user;

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($conversation, $content) {
            $userMessage = AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::User,
                'content' => $content,
                'status' => AiChatMessageStatus::Completed,
            ]);

            $assistantMessage = AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'role' => AiChatMessageRole::Assistant,
                'content' => '',
                'status' => AiChatMessageStatus::Pending,
            ]);

            return [$userMessage, $assistantMessage];
        });

        $systemPrompt = $this->promptBuilder->build($conversation, $user);
        $history = $this->promptBuilder->buildHistory($conversation->fresh());

        try {
            $response = $this->llm->chat($systemPrompt, $history);
        } catch (AiChatLlmApiException $e) {
            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error,
                'error_detail' => $e->getMessage(),
            ]);

            Log::channel('ai-chat')->error('ai-chat sync chat failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $assistantMessage->id,
                'error_class' => $e::class,
                'error_message' => $e->getMessage(),
                'http_status' => $e->httpStatus,
            ]);

            $conversation->update(['last_message_at' => now()]);

            throw new AiChatLlmFailedException(previous: $e, upstreamStatus: $e->httpStatus);
        }

        $assistantMessage->update([
            'content' => $response->content,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'response_time_ms' => $response->responseTimeMs,
            'status' => AiChatMessageStatus::Completed,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->maybeGenerateTitle($conversation);

        Log::channel('ai-chat')->info('ai-chat sync chat completed', [
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'response_time_ms' => $response->responseTimeMs,
        ]);

        return [
            'user_message' => $userMessage->fresh(),
            'assistant_message' => $assistantMessage->fresh(),
        ];
    }

    /**
     * 初回 assistant completed の直後にだけタイトル LLM 生成を試みる。
     * 失敗時 / 無効化時 は fallback タイトル (作成時の先頭 30 文字) を保持する。
     */
    private function maybeGenerateTitle(AiChatConversation $conversation): void
    {
        if (! (bool) config('ai-chat.title_generation_enabled', true)) {
            return;
        }

        $completedAssistantCount = $conversation->messages()
            ->where('role', AiChatMessageRole::Assistant->value)
            ->where('status', AiChatMessageStatus::Completed->value)
            ->count();
        if ($completedAssistantCount !== 1) {
            return;
        }

        $title = ($this->titleGenerator)($conversation);
        if ($title !== null && $title !== '') {
            $conversation->update(['title' => $title]);
        }
    }
}
