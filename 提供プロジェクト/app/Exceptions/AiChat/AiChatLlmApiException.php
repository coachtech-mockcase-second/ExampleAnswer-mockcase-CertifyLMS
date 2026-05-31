<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;

/**
 * LLM Repository (Gemini など) の内部で API 呼出が失敗した際に投げる Repository 内部例外。
 *
 * Action / Service 側でキャッチして、HTTP 境界へは AiChatLlmFailedException (502) に変換して再 throw する。
 * 受講生にこの例外メッセージを直接見せない (Repository 由来の HTTP ステータスや URL がログ目的で含まれるため)。
 */
final class AiChatLlmApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
