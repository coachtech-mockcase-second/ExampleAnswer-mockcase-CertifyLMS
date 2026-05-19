<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 未解決 (open) のスレッドに対し unresolve を実行しようとした場合の例外 (HTTP 409)。
 */
final class QaThreadNotResolvedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('このスレッドは未解決です。', $previous);
    }
}
