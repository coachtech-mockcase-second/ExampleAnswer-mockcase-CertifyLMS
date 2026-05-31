<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * NotStarted 以外の状態でキャンセル操作が呼ばれた際の例外(HTTP 409)。
 *
 * 受験中(InProgress) / 提出済(Submitted) / 採点完了(Graded) のセッションはキャンセル不可。
 */
final class MockExamSessionNotCancelableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('未開始の受験セッションのみキャンセルできます。', $previous);
    }
}
