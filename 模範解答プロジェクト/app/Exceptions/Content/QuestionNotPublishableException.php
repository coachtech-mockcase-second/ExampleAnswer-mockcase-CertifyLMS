<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuestionNotPublishableException extends ConflictHttpException
{
    public function __construct(
        string $message = '公開には選択肢 2 件以上 + 正答 1 件が必要です。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
