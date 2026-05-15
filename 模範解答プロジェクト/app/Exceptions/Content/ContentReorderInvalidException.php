<?php

namespace App\Exceptions\Content;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ContentReorderInvalidException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '並び順の指定が不正です。同一親配下の全 ID を 1..N の連番で指定してください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
