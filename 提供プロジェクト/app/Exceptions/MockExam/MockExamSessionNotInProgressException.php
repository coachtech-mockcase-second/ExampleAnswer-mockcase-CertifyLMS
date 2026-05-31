<?php

declare(strict_types=1);

namespace App\Exceptions\MockExam;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 解答 PATCH や提出を InProgress 以外の状態から呼んだ際の例外(HTTP 409)。
 *
 * 二重提出ガード(lockForUpdate 後の状態再確認) でも本例外が発火し得る。
 */
final class MockExamSessionNotInProgressException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この受験セッションは現在受験中ではありません。', $previous);
    }
}
