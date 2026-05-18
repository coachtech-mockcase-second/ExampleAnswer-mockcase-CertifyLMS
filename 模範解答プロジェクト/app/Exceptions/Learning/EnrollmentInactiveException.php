<?php

declare(strict_types=1);

namespace App\Exceptions\Learning;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 学習中止(failed)状態の Enrollment に対して読了マーク / 学習セッション開始が試みられた際の例外。
 * HTTP 409 Conflict にマップされる。`learning` / `passed` は通過(passed は復習として許容)。
 */
final class EnrollmentInactiveException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('学習中止された資格のため、学習操作はできません。', $previous);
    }
}
