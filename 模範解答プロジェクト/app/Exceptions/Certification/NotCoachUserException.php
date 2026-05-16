<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class NotCoachUserException extends UnprocessableEntityHttpException
{
    public function __construct(
        string $message = '指定したユーザーはコーチではありません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
