<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuestionInUseException extends ConflictHttpException
{
    public function __construct(
        string $message = 'この問題は mock-exam で使用中のため削除できません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
