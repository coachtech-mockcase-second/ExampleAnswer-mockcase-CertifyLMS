<?php

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserAlreadyWithdrawnException extends ConflictHttpException
{
    public function __construct(
        string $message = '対象ユーザーは既に退会済みです。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
