<?php

declare(strict_types=1);

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 既に退会済みのユーザーに対してさらに退会処理を行おうとした際の業務違反例外。
 *
 * 退会は冪等な操作にしない(再退会は明示的な業務不整合として弾く)。
 */
final class UserAlreadyWithdrawnException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('対象ユーザーは既に退会済みです。', $previous);
    }
}
