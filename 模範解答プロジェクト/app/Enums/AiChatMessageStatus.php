<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの応答状態を表す Enum。assistant role のみ意味を持ち、user は INSERT 直後に Completed。
 *
 * - Pending: assistant 作成直後 (LLM 呼出前)
 * - Streaming: SSE chunk 受信中 (SSE 版でのみ採用)
 * - Completed: LLM 応答完了
 * - Error: LLM 呼出失敗 (受講生は再送信ボタンで復旧可能)
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Streaming = 'streaming';
    case Completed = 'completed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待機中',
            self::Streaming => '応答中',
            self::Completed => '完了',
            self::Error => 'エラー',
        };
    }
}
