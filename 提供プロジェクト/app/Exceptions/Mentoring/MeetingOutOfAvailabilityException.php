<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * 受講生が指定した開始時刻が、担当コーチ集合の有効な面談可能時間枠の外だった場合の例外。
 *
 * 予約確定時の枠検証で発火する。HTTP 422 として Handler が redirect+flash に変換する。
 */
final class MeetingOutOfAvailabilityException extends UnprocessableEntityHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('指定された時刻は面談可能時間外です。別の時刻をお選びください。', $previous);
    }
}
