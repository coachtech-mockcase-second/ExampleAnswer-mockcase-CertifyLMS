<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 受講生が published 以外(draft / archived)の追加面談 SKU を購入しようとした際に throw される(HTTP 422)。
 */
final class MeetingQuotaPlanNotPublishedException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開中の追加面談プランのみ購入できます。', $previous);
    }
}
