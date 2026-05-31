<?php

declare(strict_types=1);

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 修了条件(対象資格の公開模試すべての合格点超え)を満たしていない状態で「修了証を受け取る」操作を行った場合の拒否例外。
 */
final class CompletionNotEligibleException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('修了条件を満たしていません。公開中の模試すべての合格点を超えてからお試しください。', $previous);
    }
}
