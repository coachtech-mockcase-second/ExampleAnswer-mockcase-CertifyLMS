<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PendingInvitationAlreadyExistsException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このメールアドレスへの招待は既に保留中です。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
