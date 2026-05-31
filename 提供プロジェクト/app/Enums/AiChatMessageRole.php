<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの送り手種別を表す Enum。OpenAI Chat Completions の形式に揃える。
 *
 * - User: 受講生本人の発言 (status は INSERT 直後に Completed 固定)
 * - Assistant: AI の応答
 */
enum AiChatMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => 'あなた',
            self::Assistant => 'AI',
        };
    }
}
