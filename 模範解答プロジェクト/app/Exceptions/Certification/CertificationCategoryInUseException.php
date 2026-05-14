<?php

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CertificationCategoryInUseException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このカテゴリは資格に紐付いているため削除できません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
