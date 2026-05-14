<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EmailAlreadyRegisteredException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このメールアドレスは既に登録されています。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
