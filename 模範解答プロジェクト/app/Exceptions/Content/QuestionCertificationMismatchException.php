<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class QuestionCertificationMismatchException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '指定された Section が現在の資格と一致しません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
