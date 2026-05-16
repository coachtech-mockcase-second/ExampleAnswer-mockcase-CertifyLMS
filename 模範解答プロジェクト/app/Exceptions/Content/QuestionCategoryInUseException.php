<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuestionCategoryInUseException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このカテゴリは問題に紐付いているため削除できません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
