<?php

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentNotPassedException extends ConflictHttpException
{
    public function __construct(
        string $message = '受講登録が修了状態ではないため、修了証を発行できません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
