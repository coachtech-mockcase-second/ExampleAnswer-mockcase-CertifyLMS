<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gemini API キー等の必須設定が空のまま AI 相談機能が呼び出された場合に throw する例外。
 *
 * 500 を返し、受講生には汎用文言「AI 相談機能は現在ご利用いただけません。管理者にお問い合わせください。」を表示する。
 * 設定漏れは運用ミスのため、内部ログには十分な詳細を残しつつ受講生には抽象表示する。
 */
final class AiChatNotConfiguredException extends HttpException
{
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        parent::__construct(
            statusCode: 500,
            message: $message ?? 'AI 相談機能は現在ご利用いただけません。管理者にお問い合わせください。',
            previous: $previous,
        );
    }
}
