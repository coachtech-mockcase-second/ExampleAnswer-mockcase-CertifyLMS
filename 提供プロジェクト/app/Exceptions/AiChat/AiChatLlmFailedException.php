<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gemini など LLM API 呼出が失敗し、AI 応答を取得できなかった場合に Controller 経由で 502 を返すための例外。
 *
 * Repository 層の AiChatLlmApiException を Action 側がキャッチして変換する想定 (内部詳細を HTTP 境界に
 * 漏らさない、ログには別途 Log::channel('ai-chat')->error() で記録する)。
 */
final class AiChatLlmFailedException extends HttpException
{
    public function __construct(
        ?string $message = null,
        ?\Throwable $previous = null,
        public readonly ?int $upstreamStatus = null,
    ) {
        parent::__construct(
            statusCode: 502,
            message: $message ?? 'AI が応答できませんでした。しばらく時間をおいて再試行してください。',
            previous: $previous,
        );
    }
}
