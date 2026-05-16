<?php

declare(strict_types=1);

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SelfRoleChangeForbiddenException extends AccessDeniedHttpException
{
    public function __construct(
        string $message = '自分自身のロールは変更できません。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
