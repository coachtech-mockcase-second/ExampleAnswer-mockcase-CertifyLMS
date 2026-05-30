<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\AiChatLlmFailedException;
use App\Http\Requests\AiChatMessage\StoreRequest;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\UseCases\AiChatMessage\RetryAction;
use App\UseCases\AiChatMessage\StoreAction;
use Illuminate\Http\JsonResponse;

/**
 * AI 相談メッセージ送信 / 再送信 Controller (受講生専用)。
 *
 * - store: 同期送信 (200 + JSON {user_message, assistant_message, conversation})
 * - retry: error 状態 message を再送信 (200 + JSON {assistant_message})
 */
class AiChatMessageController extends Controller
{
    public function store(
        AiChatConversation $conversation,
        StoreRequest $request,
        StoreAction $action,
    ): JsonResponse {
        try {
            $result = $action($conversation, (string) $request->validated('content'));
        } catch (AiChatLlmFailedException $e) {
            // 上流 (Gemini) の HTTP ステータスをクライアントへ伝え、UI で原因別の文言を出せるようにする
            return response()->json([
                'message' => $e->getMessage(),
                'upstream_status' => $e->upstreamStatus,
            ], 502);
        }

        return response()->json([
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'conversation' => $conversation->fresh(),
        ]);
    }

    public function retry(AiChatMessage $message, RetryAction $action): JsonResponse
    {
        $conversation = $message->conversation;
        $this->authorize('update', $conversation);

        $assistantMessage = $action($message);

        return response()->json([
            'assistant_message' => $assistantMessage,
            'conversation' => $conversation->fresh(),
        ]);
    }
}
