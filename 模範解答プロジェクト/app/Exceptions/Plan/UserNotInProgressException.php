<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * graduated / withdrawn ユーザーに対するプラン延長操作が試行された際に throw される。
 * 再加入は新規招待で対応する。
 */
class UserNotInProgressException extends ConflictHttpException
{
    public function __construct(
        string $message = '受講中(in_progress)のユーザーのみプラン延長が可能です。卒業・退会ユーザーは新規招待で対応してください。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
