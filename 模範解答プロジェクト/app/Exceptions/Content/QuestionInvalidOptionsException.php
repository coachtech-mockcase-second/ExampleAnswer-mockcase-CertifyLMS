<?php

declare(strict_types=1);

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class QuestionInvalidOptionsException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '選択肢のうち正答は 1 件のみ指定してください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
