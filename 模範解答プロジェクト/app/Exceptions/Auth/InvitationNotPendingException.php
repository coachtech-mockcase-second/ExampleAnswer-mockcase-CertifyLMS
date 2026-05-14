<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InvitationNotPendingException extends ConflictHttpException
{
    public function __construct(
        string $message = 'この招待は既に処理済みのため取り消せません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
