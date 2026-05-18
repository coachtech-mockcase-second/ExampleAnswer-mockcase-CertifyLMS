<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * セッション開始(start) を NotStarted 以外の状態から呼んだ際の例外(HTTP 409)。
 *
 * 既に InProgress / Submitted / Graded / Canceled に遷移済のセッションは再度 start できない。
 */
final class MockExamSessionAlreadyStartedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この受験セッションは既に開始済または終了済です。', $previous);
    }
}
