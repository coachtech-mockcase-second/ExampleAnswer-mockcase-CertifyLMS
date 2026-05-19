<?php

declare(strict_types=1);

namespace App\Exceptions\Notification;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 管理者お知らせの target_type と関連 FK 列の組み合わせが不整合な場合に投げる例外。
 * 例: target_type=Certification なのに target_certification_id が NULL / target_type=AllStudents なのに FK が指定されている等。
 */
final class AdminAnnouncementInvalidTargetException extends UnprocessableEntityHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('配信対象の指定が不整合です。target_type に対応するフィールドが正しく指定されているか確認してください。', $previous);
    }
}
