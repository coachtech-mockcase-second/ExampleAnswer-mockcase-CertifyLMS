<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 修了状態ではない受講登録に対して修了証を発行しようとした際の例外（HTTP 409）。
 * Enrollment の `status === Passed` かつ `passed_at !== null` を満たさない場合に `Certificate\IssueAction` から throw される。
 */
final class EnrollmentNotPassedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('受講登録が修了状態ではないため、修了証を発行できません。', $previous);
    }
}
