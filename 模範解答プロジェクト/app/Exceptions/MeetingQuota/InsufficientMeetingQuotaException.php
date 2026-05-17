<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 受講生の残面談回数が 0 の状態で面談予約が試行された際に throw される(HTTP 409)。
 * 解消するには追加面談購入動線(/meeting-quota/checkout)で SKU を購入するか、管理者の手動付与を待つ。
 */
final class InsufficientMeetingQuotaException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '面談回数が不足しています。追加面談を購入するか、管理者にお問い合わせください。',
            $previous,
        );
    }
}
