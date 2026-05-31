<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

/**
 * 面談開始時刻を過ぎた予約をキャンセルしようとした場合の例外。
 *
 * HTTP 409 として Handler が redirect+flash に変換する。
 */
final class MeetingAlreadyStartedException extends ConflictHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('面談開始時刻を過ぎた予約はキャンセルできません。', $previous);
    }
}
