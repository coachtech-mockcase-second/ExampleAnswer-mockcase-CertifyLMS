<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 紐付く資格があるカテゴリを削除しようとした際の例外（HTTP 409）。
 * `CertificationCategory\DestroyAction` から throw され、admin に対して整理を促す。
 */
final class CertificationCategoryInUseException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('このカテゴリは資格に紐付いているため削除できません。', $previous);
    }
}
