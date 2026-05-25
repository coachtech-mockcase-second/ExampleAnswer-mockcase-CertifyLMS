<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プランの status 遷移違反(draft → archived 直接遷移など)が試行された際に throw される(HTTP 409)。
 * 正規遷移は draft → published → archived → draft (unarchive)。
 *
 * 状態遷移ごとのメッセージは static ファクトリで提供する(呼出側に文字列責務を持たせない)。
 */
final class PlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書きのプランのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中のプランのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みのプランのみ下書きへ戻せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
