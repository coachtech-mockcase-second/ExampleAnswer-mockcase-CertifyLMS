<?php

declare(strict_types=1);

namespace App\Exceptions\Notification;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 管理者お知らせの target_certification / target_user が存在しない場合に投げる例外。
 * FormRequest の `exists` 検証と二重防御の意味で Action 側でも検査する。
 */
final class AdminAnnouncementTargetNotFoundException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('指定された配信対象が見つかりません。', $previous);
    }
}
