<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 「再送信」操作の対象として不適切なメッセージ (例: status が error 以外) に対して
 * 受講生が retry を呼び出した場合に throw する例外。
 *
 * 422 で受講生に「このメッセージは再送信できません」を返し、UI 側で再送信ボタンを抑止できる。
 */
final class AiChatMessageNotRetryableException extends UnprocessableEntityHttpException
{
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        parent::__construct(
            message: $message ?? 'このメッセージは再送信できません(エラー状態のメッセージのみ再送信可能です)。',
            previous: $previous,
        );
    }
}
