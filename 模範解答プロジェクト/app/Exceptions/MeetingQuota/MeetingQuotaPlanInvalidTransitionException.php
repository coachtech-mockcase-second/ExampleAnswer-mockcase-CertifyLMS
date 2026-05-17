<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 追加面談 SKU マスタの状態遷移違反(draft → archived 直接遷移など)が試行された際に throw される(HTTP 409)。
 * 正規遷移は draft → published → archived → draft (unarchive)。
 *
 * 状態遷移ごとのメッセージは static ファクトリで提供する(呼出側に文字列責務を持たせない)。
 */
final class MeetingQuotaPlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書きの追加面談プランのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中の追加面談プランのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みの追加面談プランのみ下書きへ戻せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
