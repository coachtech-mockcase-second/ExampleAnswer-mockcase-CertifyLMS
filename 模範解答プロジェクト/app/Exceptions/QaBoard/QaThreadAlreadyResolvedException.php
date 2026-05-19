<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 既に解決済 (resolved) のスレッドに対し resolve を再度実行しようとした場合の例外 (HTTP 409)。
 */
final class QaThreadAlreadyResolvedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('このスレッドは既に解決済です。', $previous);
    }
}
