<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談会話を論理削除する Action。SoftDelete のみで物理削除は行わない。
 * 受講生が間違って削除した場合の復旧 (admin 経由) を将来の拡張余地として残す。
 */
final class DestroyAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        $conversation->delete();
    }
}
