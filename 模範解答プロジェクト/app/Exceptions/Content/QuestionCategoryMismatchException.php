<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class QuestionCategoryMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '指定された出題分野は現在の資格に属していません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
