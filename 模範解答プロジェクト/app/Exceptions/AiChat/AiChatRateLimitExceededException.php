<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * AI 相談の日次送信上限を超過したリクエストに対して 429 を返すための例外。
 *
 * メッセージに上限値 (1 日 50 通など) を埋め込んで受講生に明示する。Gemini 無料枠の保護目的なので、
 * 上限値は config('ai-chat.daily_message_limit') が SSoT となる。
 */
final class AiChatRateLimitExceededException extends HttpException
{
    public function __construct(?int $limit = null, ?\Throwable $previous = null)
    {
        $limit = $limit ?? (int) config('ai-chat.daily_message_limit', 50);
        parent::__construct(
            statusCode: 429,
            message: "本日の利用上限({$limit} 通)に達しました。明日 0:00 以降に再度ご利用ください。",
            previous: $previous,
        );
    }
}
