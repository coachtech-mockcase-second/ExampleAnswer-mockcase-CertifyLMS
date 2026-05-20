<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談会話のタイトル編集ユースケース。title 1 カラムのみ更新する。
 */
final class UpdateAction
{
    /**
     * @param array{title: string} $validated
     */
    public function __invoke(AiChatConversation $conversation, array $validated): AiChatConversation
    {
        $conversation->update(['title' => $validated['title']]);

        return $conversation->fresh();
    }
}
