<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 修了済(passed)の Enrollment への状態変更操作を拒否する例外。修了後の操作は admin 含めすべて拒否する。
 */
final class EnrollmentAlreadyPassedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('修了済みの受講登録は変更できません。', $previous);
    }
}
