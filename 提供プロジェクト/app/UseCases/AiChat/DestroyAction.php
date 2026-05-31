<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談会話を物理削除する Action。配下のメッセージは外部キーの cascade で連動削除される。
 */
final class DestroyAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        $conversation->delete();
    }
}
