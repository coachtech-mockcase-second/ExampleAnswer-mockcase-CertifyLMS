<?php

declare(strict_types=1);

namespace App\UseCases\AiChatMessage;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatMessageNotRetryableException;
use App\Models\AiChatMessage;

/**
 * Error 状態の assistant メッセージを再生成する Action。
 *
 * - 対象が assistant role かつ status=Error でなければ AiChatMessageNotRetryableException (422)
 * - 該当 assistant メッセージ自体は SoftDelete せず、直前の user メッセージを再度 LLM に投げて
 *   pending → completed (or error) に書き換える
 * - 元の error_detail は新しい結果で上書き / クリアされる
 */
final class RetryAction
{
    public function __construct(
        private readonly StoreAction $store,
    ) {}

    /**
     * @throws AiChatMessageNotRetryableException
     */
    public function __invoke(AiChatMessage $message): AiChatMessage
    {
        if ($message->role !== AiChatMessageRole::Assistant || $message->status !== AiChatMessageStatus::Error) {
            throw new AiChatMessageNotRetryableException;
        }

        $conversation = $message->conversation;

        // 直前の user メッセージを取得 (assistant の直前にあるはず)
        $userMessage = $conversation->messages()
            ->where('role', AiChatMessageRole::User->value)
            ->where('created_at', '<=', $message->created_at)
            ->orderByDesc('created_at')
            ->first();

        if ($userMessage === null) {
            throw new AiChatMessageNotRetryableException(
                '再送信のために必要な直前の質問メッセージが見つかりません。',
            );
        }

        // エラー状態の assistant メッセージ自体は履歴に含めないため (PromptBuilderService が
        // status!=error でフィルタ)、StoreAction の再呼出で新しい user + assistant ペアを追加する形を取る。
        // ただし「同じ質問の再送」の UX を維持するため、ここでは元の user メッセージの content を再利用する。
        $result = ($this->store)($conversation, (string) $userMessage->content);

        // 古い error メッセージは Cascade で残置 (履歴の整合性のため、physical delete はしない)
        return $result['assistant_message'];
    }
}
