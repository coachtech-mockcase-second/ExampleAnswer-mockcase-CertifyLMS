<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * 自動コーチ割当で候補が 0 名だった場合、または DB UNIQUE 制約 race condition で
 * 同時刻に別の受講生が先に予約を確定させた場合の例外。
 *
 * HTTP 409 として Handler が redirect+flash に変換する。
 */
final class MeetingNoAvailableCoachException extends ConflictHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('指定された時刻には空きコーチがいません。別の時刻をお選びください。', $previous);
    }
}
