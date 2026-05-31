<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 同一 Enrollment に対して修了証が二重発行されようとした際の例外（HTTP 409）。
 * `enrollment_id` UNIQUE 制約違反を `Certificate\IssueAction` 内で catch して本例外に変換する。
 */
final class CertificateAlreadyIssuedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この受講登録の修了証はすでに発行されています。', $previous);
    }
}
