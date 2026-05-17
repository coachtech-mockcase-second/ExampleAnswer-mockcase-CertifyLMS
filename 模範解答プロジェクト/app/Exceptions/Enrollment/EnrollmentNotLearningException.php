<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 学習中(learning)状態でしか行えない操作を、他状態の Enrollment に対して実行しようとした場合の拒否例外。
 * 主に「修了証を受け取る」操作で学習中以外の状態を弾く際に使う。
 */
final class EnrollmentNotLearningException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('学習中の受講登録のみこの操作を行えます。', $previous);
    }
}
