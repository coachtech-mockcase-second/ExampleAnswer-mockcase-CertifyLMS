<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談会話 1 件と紐づくメッセージ全件を Eager Load して返す Action。
 *
 * 受講生がフル画面の会話詳細を開いた時に Controller から呼ばれる。messages は created_at 昇順で
 * 並ぶことを保証し、画面側でそのまま順番に描画できるようにする。
 */
final class ShowAction
{
    public function __invoke(AiChatConversation $conversation): AiChatConversation
    {
        return $conversation->load([
            'enrollment.certification',
            'section.chapter.part',
            'messages' => fn ($query) => $query->orderBy('created_at'),
        ]);
    }
}
