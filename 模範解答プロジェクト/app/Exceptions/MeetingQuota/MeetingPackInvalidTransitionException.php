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
final class MeetingPackInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書きの面談パックのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中の面談パックのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みの面談パックのみ下書きへ戻せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
