<?php

declare(strict_types=1);

namespace App\Exceptions\UserManagement;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 退会処理を行うと残存する admin が 0 になる場合の業務違反例外。
 *
 * 「LMS から admin が完全に消滅する」状態は運用上許容しないため、最後の 1 人の admin の退会を拒否する。
 */
final class LastAdminWithdrawException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('最後の管理者は退会できません。', $previous);
    }
}
