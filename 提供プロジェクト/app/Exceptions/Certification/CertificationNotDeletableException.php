<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない資格マスタを削除しようとした際の例外（HTTP 409）。
 * `Certification\DestroyAction` が「下書き状態のみ削除可」のドメインルールから throw する。
 */
final class CertificationNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('下書き状態の資格のみ削除できます。', $previous);
    }
}
