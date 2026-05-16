<?php

declare(strict_types=1);

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SelfWithdrawForbiddenException extends AccessDeniedHttpException
{
    public function __construct(
        string $message = '自分自身を退会させることはできません。退会は設定画面から行ってください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
