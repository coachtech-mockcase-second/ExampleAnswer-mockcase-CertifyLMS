<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 同一 user × certification の Enrollment が既に存在する場合の二重登録ガード例外。
 */
final class EnrollmentAlreadyEnrolledException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('既にこの資格を受講中です。', $previous);
    }
}
